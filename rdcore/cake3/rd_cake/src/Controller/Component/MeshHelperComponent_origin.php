<?php
//----------------------------------------------------------
//---- Author: Dirk van der Walt
//---- License: GPL v3
//---- Description: A component that is used to record Unknown thing, e.g Nodes, Access Points etc
//---- Date: 05-05-2018
//------------------------------------------------------------

namespace App\Controller\Component;
use Cake\Controller\Component;
use Cake\ORM\TableRegistry;

use Cake\Core\Configure;
use Cake\Core\Configure\Engine\PhpConfig;

class MeshHelperComponent extends Component {

    protected $RadioSettings = [];

    public function initialize(array $config){
        //Please Note that we assume the Controller has a JsonErrors Component Included which we can access.
        $this->controller       = $this->_registry->getController();
        $this->Meshes           = TableRegistry::get('Meshes');
        $this->Nodes            = TableRegistry::get('Nodes');
        $this->OpenVpnServers   = TableRegistry::get('OpenVpnServers');
        $this->NodeWifiSettings = TableRegistry::get('NodeWifiSettings');
        $this->Devices          = TableRegistry::get('Devices');
        $this->UserSettings     = TableRegistry::get('UserSettings');
        $this->Hardwares        = TableRegistry::get('Hardwares');
    }

    public function JsonForMeshNode($ent_node,$gw){
    
        $mesh_id        = $ent_node->mesh_id;
        $this->NodeId   = $ent_node->id;
	    $this->Hardware	= $ent_node->hardware;
		$this->Power	= $ent_node->power;
		$this->Mac      = $ent_node->mac;
		$this->EntNode  = $ent_node;
        
        $ent_mesh = $this->Meshes->find()
                    ->where(['Meshes.id' => $mesh_id])
                    ->contain([
                        'MeshExits.MeshExitMeshEntries', 
                        'MeshEntries',
                        'NodeSettings',
                        'MeshSettings',
                        'MeshExits.MeshExitCaptivePortals',
                        'MeshExits.OpenvpnServerClients',
                        'Nodes' => ['NodeMeshEntries']
                        ])
                    ->first();
                    
        if($ent_mesh){    
			$this->_update_fetched_info($ent_node);
            $json = $this->_build_json($ent_mesh,$gw);
            return $json; 
        }
    }
    
    private function _update_fetched_info($ent_node){
        //--Update the fetched info--
        $data = [];
		$data['id'] 			        = $this->NodeId;
		$data['config_fetched']         = date("Y-m-d H:i:s", time());
		$data['last_contact_from_ip']   = $this->request->clientIp();
        $this->Nodes->patchEntity($ent_node, $data);
        $this->Nodes->save($ent_node);
    }
    
    private function  _build_json($ent_mesh,$gateway = false){

        //Basic structure
        $json                                   = [];
        $json['timestamp']                      = 1; //FIXME replace with the last change timestamp
        $json['config_settings']                = [];
        $json['config_settings']['wireless']    = [];
        $json['config_settings']['network']     = [];
		$json['config_settings']['system']		= [];

        //============ Network ================
        $net_return         = $this->_build_network($ent_mesh,$gateway);
        $json_network       = $net_return[0];
        $json['config_settings']['network'] = $json_network;

        //=========== Wireless ===================
        $entry_data         = $net_return[1];
        $json_wireless      = $this->_build_wireless($ent_mesh,$entry_data);
        $json['config_settings']['wireless'] = $json_wireless;
        
        //========== Gateway or NOT? ======
        if($gateway){   
            $json['config_settings']['gateways']        = $net_return[2]; //Gateways
            $json['config_settings']['captive_portals'] = $net_return[3]; //Captive portals
                             
            $openvpn_bridges                            = $this->_build_openvpn_bridges($net_return[4]);
            $json['config_settings']['openvpn_bridges'] = $openvpn_bridges; //Openvpn Bridges
        }
        

		//======== System related settings ======
		$system_data 		= $this->_build_system($ent_mesh);
		$json['config_settings']['system'] = $system_data;
		

		//====== Batman-adv specific config settings ======
		Configure::load('MESHdesk');
        $batman_adv       = Configure::read('mesh_settings'); //Read the defaults
		if($ent_mesh->mesh_setting != null){
			unset($ent_mesh->mesh_setting->id);
			unset($ent_mesh->mesh_setting->mesh_id);
			unset($ent_mesh->mesh_setting->created);
			unset($ent_mesh->mesh_setting->modified);
			$batman_adv = $ent_mesh->mesh_setting;
		}
		$json['config_settings']['batman_adv'] = $batman_adv;
        return $json; 
    }
    
    
	private function _build_system($ent_mesh){
		//Get the root password
		$ss = array();
		if($ent_mesh->node_setting !== null && $ent_mesh->node_setting->password_hash != ''){
			$ss['password_hash'] 		= $ent_mesh->node_setting->password_hash;
			$ss['heartbeat_interval']	= $ent_mesh->node_setting->heartbeat_interval;
			$ss['heartbeat_dead_after']	= $ent_mesh->node_setting->heartbeat_dead_after;
		}else{
			Configure::load('MESHdesk');
			$data = Configure::read('common_node_settings'); //Read the defaults
			$ss['password_hash'] 		= $data['password_hash'];
			$ss['heartbeat_interval']	= $data['heartbeat_interval'];
			$ss['heartbeat_dead_after']	= $data['heartbeat_dead_after'];
		}

       
        //Timezone
        if($ent_mesh->node_setting !== null && $ent_mesh->node_setting->tz_value != ''){
            $ss['timezone']             = $ent_mesh->node_setting->tz_value;
        }else{
            Configure::load('MESHdesk');
			$data = Configure::read('common_node_settings'); //Read the defaults
            $ss['timezone']             = $data['tz_value'];
        }

	    //Syslog Server 1
	    if($ent_mesh->node_setting !== null && $ent_mesh->node_setting->syslog1_ip != ''){
		    $ss['syslog1_ip'] = $ent_mesh->node_setting->syslog1_ip;
                    $ss['syslog1_port'] = $ent_mesh->node_setting->syslog1_port;
	    }else{
		    Configure::load('MESHdesk');
		    $data = Configure::read('common_node_settings'); //Read the defaults
		    $ss['syslog1_ip'] = $data['syslog1_ip'];
		    $ss['syslog1_port'] = $data['syslog1_port'];
	    }

	    //Syslog Server 2
        if($ent_mesh->node_setting !== null && $ent_mesh->node_setting->syslog2_ip != ''){
                $ss['syslog2_ip'] = $ent_mesh->node_setting->syslog2_ip;
                $ss['syslog2_port'] = $ent_mesh->node_setting->syslog2_port;
        }else{
	    Configure::load('MESHdesk');
                $data = Configure::read('common_node_settings'); //Read the defaults
                $ss['syslog2_ip'] = $data['syslog2_ip'];
                $ss['syslog2_port'] = $data['syslog2_port'];
        }

	//Syslog Server 3
        if($ent_mesh->node_setting !== null && $ent_mesh->node_setting->syslog3_ip != ''){
                $ss['syslog3_ip'] = $ent_mesh->node_setting->syslog3_ip;
                $ss['syslog3_port'] = $ent_mesh->node_setting->syslog3_port;
        }else{
		Configure::load('MESHdesk');
                $data = Configure::read('common_node_settings'); //Read the defaults
                $ss['syslog3_ip'] = $data['syslog3_ip'];
                $ss['syslog3_port'] = $data['syslog3_port'];
        }

        //Gateway specifics
        if($ent_mesh->node_setting !== null && $ent_mesh->node_setting->gw_dhcp_timeout != ''){
            $ss['gw_dhcp_timeout']          = $ent_mesh->node_setting->gw_dhcp_timeout;
            $ss['gw_use_previous']          = $ent_mesh->node_setting->gw_use_previous;
            $ss['gw_auto_reboot']           = $ent_mesh->node_setting->gw_auto_reboot;
            $ss['gw_auto_reboot_time']      = $ent_mesh->node_setting->gw_auto_reboot_time; 
        }else{
            Configure::load('MESHdesk');
			$data = Configure::read('common_node_settings'); //Read the defaults
            $ss['gw_dhcp_timeout']          = $data['gw_dhcp_timeout'];
            $ss['gw_use_previous']          = $data['gw_use_previous'];
            $ss['gw_auto_reboot']           = $data['gw_auto_reboot'];
            $ss['gw_auto_reboot_time']      = $data['gw_auto_reboot_time'];
        }
		
		$ss['hostname'] = $this->EntNode->name;
		
		//System Specific Settings
		$want_these = ['mqtt_user','mqtt_password', 'mqtt_server_url', 'mqtt_command_topic'];
		$ent_us = $this->UserSettings->find()->where(['UserSettings.user_id' => -1])->all();
		
		foreach($ent_us as $s){
		    $s_name     = $s->name;
		    $s_value    = $s->value;
		    if(in_array($s_name,$want_these)){
		        $ss["$s_name"] = $s_value;
		    }
		}
		return $ss;
	}
    
    
     private function _build_network($ent_mesh,$gateway = false){

        $network 				= [];
        $nat_data				= [];
        $captive_portal_data 	= [];
        $openvpn_bridge_data    = [];
		$include_lan_dhcp 		= true;


        //=================================
        //loopback if
        array_push( $network,
            [
                "interface"    => "loopback",
                "options"   => [
                    "ifname"        => "lo",
                    "proto"         => "static",
                    "ipaddr"        => "127.0.0.1",
                    "netmask"       => "255.0.0.0"
               ]
            ]);
        //========================

		//We add a new feature - we can specify for NON Gateway nodes to which their LAN port should be connected with
		if($ent_mesh->node_setting !== null && $ent_mesh->node_setting->eth_br_chk != ''){
			$eth_br_chk 		= $ent_mesh->node_setting->eth_br_chk;
			$eth_br_with	    = $ent_mesh->node_setting->eth_br_with;
			$eth_br_for_all	    = $ent_mesh->node_setting->eth_br_for_all;
		}else{
	    	Configure::load('MESHdesk');
			$c_n_s 				= Configure::read('common_node_settings'); //Read the defaults
			$eth_br_chk 		= $c_n_s['eth_br_chk'];
			$eth_br_with	    = $c_n_s['eth_br_with'];
			$eth_br_for_all	    = $c_n_s['eth_br_for_all'];
		}

		$lan_bridge_flag 	= false;
		

		//If we need to bridge and it is with the LAN (the easiest)
		if(
			($eth_br_chk)&&
			($eth_br_with == 0)
		){
			$lan_bridge_flag = true;
		}

        //LAN
		$br_int = $this->_eth_br_for($this->Hardware);
		
		if($gateway == true){
		    if($this->Vlan){
		        $br_int = $br_int.'.'.$this->Vlan;
		    }
		}
		
		if($lan_bridge_flag){
			$br_int = "$br_int bat0.100";
		}

		//If we need to bridge and it is NOT with the LAN (more involved)
		if(
			($eth_br_chk)&&
			($eth_br_with != 0)&&
			($gateway == false) //Only on non-gw nodes
		){
			$include_lan_dhcp = false; //This case we do not include the lan dhcp bridge
		}
        //==================
        

		if($include_lan_dhcp){

			//We need to se the non-gw nodes to have:
			//1.) DNS Masq must not be running
			//2.) The LAN must now have DHCP client since this will trigger the setup script as soon as the interface get an IP
			//3.) This will cause a perpetiual loop since it will kick off the setup script and reconfigure itself.
			//4.) The gateway however still needs to maintain its dhcp client status.
			$proto = 'dhcp';
			if(($lan_bridge_flag)&&($gateway == false)){
				$proto = 'static';
			}
			
			//SMALL HACK START 
			$m = $this->request->query['mac'];
            $m = strtolower($m);
            $m = str_replace('-', ':', $m);
            //SMALL HACK END

		    array_push( $network,
		        [
		            "interface"    => "lan",
		            "options"   => [
		                "ifname"        => "$br_int", 
		                "type"          => "bridge",
		                "proto"         => "$proto",
		                "macaddr"       => "$m" //SMALL HACK II
		           ]
		   	]);
		}

		//Add an interface called b to list the batman interface
		array_push( $network,
            [
                "interface"    => "b",
                "options"   => [
                    "ifname"    => "bat0"
               ]
            ]);
		
        //Mesh
        array_push( $network,
            [
                "interface"    => "mesh",
                "options"   => [
                    "mtu"       => "1560",
                    "proto"     => "batadv",
                    "mesh"      => "bat0"
               ]
            ]);

        $ip = $this->EntNode->ip;

        //Admin interface
        array_push($network,
            [
                "interface"    => "one",
                "options"   => [
                    "ifname"    => "bat0.1",
                    "proto"     => "static",
                    "ipaddr"    => $ip,
                    "netmask"   => "255.255.255.0",
                    "type"      => "bridge"
               ]
            ]);

		//***With its VLAN***
		 array_push($network,
            [
                "interface"    => "bat_vlan_one",
                "options"   => [
                    "ifname"    	=> "bat0.1",
                    "proto"     	=> "batadv_vlan",
                    'ap_isolation' 	=> '0'
               ]
            ]);

        //================================

        //Now we will loop all the defined exits **that has entries assigned** to them and add them as bridges as we loop. 
        //The members of these bridges will be determined by which entries are assigned to them and specified
        //in the wireless configuration file

        $start_number = 2;

        //We create a data structure which will be used to add the entry points and bridge them with
        //The correct network defined here
        $entry_point_data = [];

        //Add the auto-attach entry points
        foreach($ent_mesh->mesh_exits as $me){
        
            $has_entries_attached   = false;
            $if_name                = 'ex_'.$this->_number_to_word($start_number);
            $exit_id                = $me->id;
            $type                   = $me->type;
            $vlan                   = $me->vlan;
            $openvpn_server_id      = $me->openvpn_server_id;
            
            //This is used to fetch info eventually about the entry points
            if(count($me->mesh_exit_mesh_entries) > 0){
                $has_entries_attached = true;
                foreach($me->mesh_exit_mesh_entries as $entry){
                    if($entry->mesh_entry_id!=0){ //Entry id of 0 is for eth1 ...
                    
                        if(($type == 'bridge')&&($gateway)){ //The gateway needs the entry points to be bridged to the LAN
                            array_push($entry_point_data, ['network' => 'lan','entry_id' => $entry->mesh_entry_id]);
                        }else{
                            array_push($entry_point_data, ['network' => $if_name,'entry_id' => $entry->mesh_entry_id]);
                        }
                    }
                }
            }
                        
            if($type == 'tagged_bridge_l3'){
                $has_entries_attached = true;    
            }
  
            if($has_entries_attached == true){

				
                //=======================================
                //========= GATEWAY NODES ===============
                //=======================================
                $captive_portal_count = 1;

                if(($type == 'tagged_bridge')&&($gateway)){
                
                    //FIXME We are going to change the way we are doing this....
                    
					$br_int = $this->_eth_br_for($this->Hardware);
					if(preg_match('/eth1/', $br_int)){	//If it has two add both
                    	$interfaces =  "bat0.".$start_number." eth0.".$vlan; //We are not including eth1 any more...
					}else{
						$interfaces =  "bat0.".$start_number." eth0.".$vlan; //only one
					}
                    array_push($network,
                        [
                            "interface"    => "$if_name",
                            "options"   => [
                                "ifname"    => $interfaces,
                                "type"      => "bridge"
                        ]]
                    );

					//***With its VLAN***
					$nr = $this->_number_to_word($start_number);
					array_push($network,
						[
							"interface"    => "bat_vlan_".$nr,
							"options"   => [
							    "ifname"    	=> "bat0.".$start_number,
							    "proto"     	=> "batadv_vlan",
							    'ap_isolation' 	=> '0'
						   ]
					]);


                    $start_number++;
                    continue;   //We don't car about the other if's
                }
        
                if(($type == 'nat')&&($gateway)){

                    $interfaces =  "bat0.".$start_number;
                    array_push($network,
                        [
                            "interface"    => "$if_name",
                            "options"   => [
                                "ifname"    => $interfaces,
                                "type"      => "bridge",
                                'ipaddr'    =>  "10.200.".(100+$start_number).".1",
                                'netmask'   =>  "255.255.255.0",
                                'proto'     => 'static'
                        ]]
                    );

					//***With its VLAN***
					$nr = $this->_number_to_word($start_number);
					array_push($network,
						[
							"interface"    => "bat_vlan_".$nr,
							"options"   => [
							    "ifname"    	=> $interfaces,
							    "proto"     	=> "batadv_vlan",
							    'ap_isolation' 	=> '0'
						   ]
					]);


                    //Push the nat data
                    array_push($nat_data,$if_name);
                    $start_number++;
                    continue; //We dont care about the other if's
                }

                if(($type=='bridge')&&($gateway)){
                    $current_interfaces = $network[1]['options']['ifname'];
                    $interfaces =  "bat0.".$start_number;
                    $network[1]['options']['ifname'] = $current_interfaces." ".$interfaces;
                    $start_number++;
                    continue; //We dont care about the other if's
                }


                if(($type == 'captive_portal')&&($gateway)){
                
                    $eth_one_bridge = false;
                    foreach($me->mesh_exit_mesh_entries as $cp_ent){
                        if($cp_ent['mesh_entry_id'] == 0){
                            $eth_one_bridge = true;
                            break;
                        }
                    }
                    
                    //---WIP Start---
                    if($me->mesh_exit_captive_portal->dnsdesk == true){
                        $if_ip      = "10.$captive_portal_count.0.2";
                    }
                    $captive_portal_count++; //Up it for the next one
                    //---WIP END---

                    //Add the captive portal's detail
                    if($type =='captive_portal'){
                        $a = $me->mesh_exit_captive_portal;
                        $a->hslan_if = 'br-'.$if_name;
                        $a->network  = $if_name;
                        
                        //---WIP Start---
                        if($me->mesh_exit_captive_portal->dnsdesk == true){
                            $a->dns1      = $if_ip;
                            //Also sent along the upstream DNS Server to use
                            $a->upstream_dns1 = Configure::read('dnsfilter.dns1'); //Read the defaults
                            $a->upstream_dns2 = Configure::read('dnsfilter.dns2'); //Read the defaults
                        }
                        //---WIP END---
                        
                        array_push($captive_portal_data,$a);             
                    }
                    
                    //More intellegent bridging
                    $tmp_br_int = $this->_eth_br_for($this->Hardware);

                    if($eth_one_bridge == true){
                        if($tmp_br_int == 'eth1'){
                            $lan_if = "eth0";
                        }else{
                            $lan_if = "eth1";
                        }
                        $interfaces =  "bat0.".$start_number." ".$lan_if;
                    }else{
                        $interfaces =  "bat0.".$start_number;
                    }
                                      
                    array_push($network,
                        [
                            "interface"    => "$if_name",
                            "options"   => [
                                "ifname"    => $interfaces,
                                "type"      => "bridge",       
                        ]]
                    );

					//***With its VLAN***
					$nr = $this->_number_to_word($start_number);
					array_push($network,
						[
							"interface"    => "bat_vlan_".$nr,
							"options"   => [
							    "ifname"    	=> $interfaces,
							    "proto"     	=> "batadv_vlan",
							    'ap_isolation' 	=> '0'
						   ]
					]);
                    $start_number++;
                    continue; //We dont care about the other if's
                }
                
                //___ OpenVPN Bridge ________
                if(($type == 'openvpn_bridge')&&($gateway)){

                    //Add the OpenvpnServer detail
                    if($type =='openvpn_bridge'){
                       
                        $a              = $me->openvpn_server_client;
                        $a['bridge']    = 'br-'.$if_name;
                        $a['interface'] = $if_name;
                        
                        //Get the info for the OpenvpnServer
                        $ent_vpn        = $this->OpenVpnServers->find()
                            ->where(['OpenVpnServers.id' => $me->openvpn_server_client->openvpn_server_id])
                            ->first();
                        
                        $a['protocol']  = $ent_vpn->protocol;
                        $a['ip_address']= $ent_vpn->ip_address;
                        $a['port']      = $ent_vpn->port;
                        $a['vpn_mask']  = $ent_vpn->vpn_mask;
                        $a['ca_crt']    = $ent_vpn->ca_crt;   
                        
                        $a['config_preset']        = $ent_vpn->config_preset;  
                        $a['vpn_gateway_address']  = $ent_vpn->vpn_gateway_address;
                        $a['vpn_client_id']        = $me->openvpn_server_client->id;                      
                        array_push($openvpn_bridge_data,$a);
                                     
                    }
                    $interfaces =  "bat0.".$start_number;
                    array_push($network,
                        array(
                            "interface"    => "$if_name",
                            "options"   => array(
                                "ifname"    => $interfaces,
                                "type"      => "bridge",
                                'ipaddr'    => $me->openvpn_server_client->ip_address,
                                'netmask'   => $a['vpn_mask'],
                                'proto'     => 'static'
                                
                        ))
                    );

					//***With its VLAN***
					$nr = $this->_number_to_word($start_number);
					array_push($network,
						array(
							"interface"    => "bat_vlan_".$nr,
							"options"   => array(
							    "ifname"    	=> $interfaces,
							    "proto"     	=> "batadv_vlan",
							    'ap_isolation' 	=> '0'
						   )
					));
                    $start_number++;
                    continue; //We dont care about the other if's
                    
                    
                }
                
                //____ LAYER 3 Tagged Bridge ____
                if(($type == 'tagged_bridge_l3')&&($gateway)){
                
                    $interfaces     = 'eth0.'.$me->vlan;  //We only do eth0  
                    $exit_point_id  = $me->id;
                    
                    $this->l3_vlans[$exit_point_id] = $if_name;
                    if($me->proto == 'dhcp'){
                         array_push($network,
                            array(
                                "interface"    => "$if_name",
                                "options"   => array(
                                    'ifname'    => 'eth0',
                                    'type'      => '8021q',
                                    'proto'     => 'dhcp',
                                    'name'      => 'eth0.'.$me->vlan,
                                    'vid'       => $me->vlan
                            ))
                        );
                    }
                    if($me->proto == 'static'){  
                        $options = [
                            'ifname'    => 'eth0',
                            'type'      => '8021q',
                            'proto'     => $me->proto,
                            'ipaddr'    => $me->ipaddr,
                            'netmask'   => $me->netmask,
                            'gateway'   => $me->gateway,
                            'name'      => 'eth0.'.$me->vlan,
                            'vid'       => $me->vlan
                        ];
                        $lists = [];
                        if($me->dns_2 != ''){
                            array_push($lists,['dns'=> $me->dns_2]);
                        }
                        if($me->dns_1 != ''){
                            array_push($lists,['dns'=> $me->dns_1]);
                        }
                    
                        array_push($network,
                            [
                                "interface" => "$if_name",
                                "options"   => $options,
                                "lists"     => $lists
                        ]); 
                    }
                }

                //=======================================
                //==== STANDARD NODES ===================
                //=======================================

                if(($type == 'nat')||($type == 'tagged_bridge')||($type == 'bridge')||($type =='captive_portal')||($type =='openvpn_bridge')){
                    $interfaces =  "bat0.".$start_number;

					//===Check if this standard node has an ethernet bridge that has to be included here (NON LAN bridge)
					if(
						($eth_br_chk)&& 			//Eth br specified
						($eth_br_with == $exit_id) 	//Only if the current one is the one to be bridged
					){
						$interfaces = "$br_int $interfaces";
					}

                    array_push($network,
                        array(
                            "interface"    => "$if_name",
                            "options"   => array(
                                "ifname"    => $interfaces,
                                "type"      => "bridge" 
                        ))
                    );

					//***With its VLAN***
					$nr = $this->_number_to_word($start_number);
					array_push($network,
						array(
							"interface"    => "bat_vlan_".$nr,
							"options"   => array(
							    "ifname"    	=> $interfaces,
							    "proto"     	=> "batadv_vlan",
							    'ap_isolation' 	=> '0'
						   )
					));
                    $start_number++;
                    continue; //We dont care about the other if's
                }
            }
        }
       
        //Captive Portal layer2 VLAN upstream enhancement 
        $cp_counter = 0;
        foreach($captive_portal_data as $cpd){
            if($cpd['mesh_exit_upstream_id'] == 0){
                $captive_portal_data[$cp_counter]['hswan_if'] = 'br-lan';
            }else{
                $captive_portal_data[$cp_counter]['hswan_if'] = $this->l3_vlans[$cpd['mesh_exit_upstream_id']];
            }
            $cp_counter++;
        }
          
        return array($network,$entry_point_data,$nat_data,$captive_portal_data,$openvpn_bridge_data);
        
    }
    
    private function _build_wireless($ent_mesh,$entry_point_data){

        $wireless = [];
        //First get the WiFi settings wether default or specific
        $this->_setWiFiSettings();

		//Determine the radio count and configure accordingly
        $radios = 0;
	    $q_e = $this->{'Hardwares'}->find()
		    ->where(['Hardwares.fw_id' => $this->Hardware,'Hardwares.for_mesh' => true])
		    ->first();
	    if($q_e){
	        $radios = $q_e->radio_count;
	    }
		
		if($radios == 2){
			$wireless = $this->_build_dual_radio_wireless($ent_mesh,$entry_point_data);
			return $wireless;
		}

        if($radios == 1){
            $wireless = $this->_build_single_radio_wireless($ent_mesh,$entry_point_data);
			return $wireless;
        }    
    }

    private function _setWiFiSettings(){
        //First we chack if the node had the Wfi Settings

        $ent_wifi_s = $this->NodeWifiSettings->find()->where(['NodeWifiSettings.node_id' => $this->NodeId])->all();
        
        //There seems to be specific settings for the node
        if(!$ent_wifi_s->isEmpty()){   
            $ht_capab_zero  = [];
            $ht_capab_one   = [];

            foreach($ent_wifi_s as $i){
                $name  = $i->name;
                $value = $i->value;
                if($name == 'device_type'){
                    continue;
                }
                
                if(preg_match('/^radio0_/',$name)){
                    $radio_number = 0;
                }
                if(preg_match('/^radio1_/',$name)){
                    $radio_number = 1;
                }
                 
                if(preg_match('/^radio\d+_ht_capab/',$name)){
                    if($value !== ''){
                        if($radio_number == 0){
                            array_push($ht_capab_zero,$value);
                        }
                        if($radio_number == 1){
                            array_push($ht_capab_one,$value);
                        }
                    }
                }else{
                    $this->RadioSettings[$radio_number][$name] = $value; 
                }  
            }
            $this->RadioSettings[0]['radio0_ht_capab'] = $ht_capab_zero;
            
            if(isset($this->RadioSettings[1])){ //Only if it is set
                $this->RadioSettings[1]['radio1_ht_capab'] = $ht_capab_one;
            } 
                      
        }else{
            
            $q_e = $this->{'Hardwares'}->find()
                ->where(['Hardwares.fw_id' => $this->Hardware, 'Hardwares.for_mesh' => true])
                ->contain(['HardwareRadios'])
                ->first();
                
            if($q_e){
            
                $radio_fields = [
                    'disabled','hwmode','htmode','txpower','include_beacon_int','beacon_int',
                    'include_distance','distance','ht_capab'
                ];
                
                foreach($q_e->hardware_radios as $hr){
                    $radio_number   = $hr->radio_number;
                    $prefix = 'radio'.$radio_number.'_';
                    $ht_capab = [];
                    foreach($radio_fields as $fr){ 
                        if($fr == 'hwmode'){
                            if($hr->{"$fr"} == '11ac'){
                                $this->RadioSettings[$radio_number]["$prefix$fr"] = '11a';
                            }else{
                                $this->RadioSettings[$radio_number]["$prefix$fr"] = $hr->{"$fr"};
                            }
                        }elseif($fr == 'ht_capab'){
                            if($hr->{"$fr"} !== ''){
                                $pieces = explode("\n", $hr->{"$fr"});
                                if(count($pieces)>0){
                                    foreach($pieces as $p){
                                        array_push($ht_capab,$p);
                                    }
                                }else{
                                    array_push($ht_capab,$hr->{"$fr"}); //Single value
                                }
                            }
                        }else{
                            $this->RadioSettings[$radio_number]["$prefix$fr"] = $hr->{"$fr"};
                        }
                    }
                    $this->RadioSettings[$radio_number]["$prefix".'ht_capab'] = $ht_capab;
                }   
            }                  
        }
    }
    
    private function _build_single_radio_wireless($ent_mesh,$entry_point_data){
    
        $wireless = array();
        $five_g_flag = false;
        
        if($ent_mesh->node_setting !== null && $ent_mesh->node_setting->client_key!='') {        
            $client_key = $ent_mesh->node_setting->client_key;
        }else{
            Configure::load('MESHdesk');
		    $client_key = Configure::read('common_node_settings.client_key');
        }

        //Get the channel
        if($ent_mesh->node_setting !== null && $ent_mesh->node_setting->two_chan!='') {        
            $channel    = $ent_mesh->node_setting->two_chan;
        }else{
            Configure::load('MESHdesk');
		    $channel = Configure::read('common_node_settings.two_chan');
        }

        //Get the hwmode for radio '0';
		$hwmode		= '11g';//Sane default	
		$include_config = true; //Sane defaults for single radio
		$include_mesh   = true;
		$include_ap     = true;
		
		$q_e = $this->{'Hardwares'}->find()
		    ->where(['Hardwares.fw_id' => $this->Hardware,'Hardwares.for_mesh' => true])
		    ->contain(['HardwareRadios'])
		    ->first();
		
	    if($q_e){       
	        foreach($q_e->hardware_radios as $hr){
	            if($hr->radio_number == 0){
	                if($hr->hwmode == '11a_ac'){
	                    $hwmode = '11a';
	                }else{
	                    $hwmode = $hr->hwmode;
	                }
	                $include_config = $hr->default_config;
	                break; //Break the loop we found our guy
	            }
	        } 
	    }

		//Channel (if 5)
		if($hwmode == '11a'){
		    $five_g_flag = true;	
		    if($ent_mesh->node_setting !== null && $ent_mesh->node_setting->five_chan!='') {
                $channel    = $ent_mesh->node_setting->five_chan;
            }else{
                Configure::load('MESHdesk');
		        $channel = Configure::read('common_node_settings.five_chan');
            }	
		}

        //Country
        if($ent_mesh->node_setting !== null && $ent_mesh->node_setting->country != ''){
            $country  = $ent_mesh->node_setting->country;
		}else{
			Configure::load('MESHdesk');
			$data       = Configure::read('common_node_settings'); //Read the defaults
            $country    = $data['country'];
		}

        $radio_zero_capab = [];
        //Somehow the read thing reads double..
        $allready_there = [];
        foreach($this->RadioSettings[0]['radio0_ht_capab'] as $c){
            if(!in_array($c,$allready_there)){
                array_push($allready_there,$c);
                array_push($radio_zero_capab,array('name'    => 'ht_capab', 'value'  => $c));
            }
        }
        
        //Required
        $options_array = [
            'channel'       => intval($channel),
            'disabled'      => 0,
            'hwmode'        => $hwmode,
            'htmode'        => $this->RadioSettings[0]['radio0_htmode'],
            'country'       => $country,
            'txpower'       => intval($this->RadioSettings[0]['radio0_txpower'])
        ];
        
        //For now we have these binary options that we obity if not specified
        if(isset($this->RadioSettings[0]['radio0_include_distance'])){
            $options_array['distance'] = intval($this->RadioSettings[0]['radio0_distance']);
        }
        
        if(isset($this->RadioSettings[0]['radio0_include_beacon_int'])){
            $options_array['beacon_int'] = intval($this->RadioSettings[0]['radio0_beacon_int']);
        }
        //END Bin options

        array_push( $wireless,
                array(
                    "wifi-device"   => "radio0",
                    "options"       => $options_array,
                    'lists'         => $radio_zero_capab
                ));

        //Get the mesh's BSSID and SSID
        $bssid      = $ent_mesh->bssid;
        $ssid       = $ent_mesh->ssid;
        
        //Get the connection type (IBSS or mesh_point);
        if($ent_mesh->mesh_setting != null){  
            $connectivity   = $ent_mesh->mesh_setting->connectivity;
            $encryption     = $ent_mesh->mesh_setting->encryption;
            $encryption_key = $ent_mesh->mesh_setting->encryption_key;
        }else{
            Configure::load('MESHdesk');
		    $connectivity   = Configure::read('mesh_settings.connectivity');
		    $encryption     = Configure::read('mesh_settings.encryption');
            $encryption_key = Configure::read('mesh_settings.encryption_key');
        }

        //Add the ad-hoc if for mesh
        $zero = $this->_number_to_word(0);
        
        //--MESH Part--
        if($include_mesh){ //(For future manipulation)
        
            if($connectivity == 'IBSS'){
                array_push( $wireless,
                        array(
                            "wifi-iface"   => "$zero",
                            "options"       => array(
                                "device"        => "radio0",
                                "ifname"        => "mesh0",
                              //  "macaddr"       => $this->_create_mac($this->Mac,'aa'),
                                "network"       => "mesh",
                                "mode"          => "adhoc",
                                "ssid"          => $ssid,
                                "bssid"         => $bssid
                            )
                        ));
            }
             
            if(($connectivity == 'mesh_point')&&(!$encryption)){
                array_push( $wireless,
                        array(
                            "wifi-iface"   => "$zero",
                            "options"       => array(
                                "device"        => "radio0",
                                "ifname"        => "mesh0",
                                "network"       => "mesh",
                                "mode"          => "mesh",
                                "mesh_id"       => $ssid,
                                "mcast_rate"    => 18000,
                                "disabled"      => 0,
                                "mesh_ttl"      => 1,
                                "mesh_fwding"   => 0,
                                "encryption"    => 'none'
                            )
                        ));
            }
            
            if(($connectivity == 'mesh_point')&&($encryption)){
                array_push( $wireless,
                        array(
                            "wifi-iface"   => "$zero",
                            "options"       => array(
                                "device"        => "radio0",
                                "ifname"        => "mesh0",

                                "network"       => "mesh",
                                "mode"          => "mesh",
                                "mesh_id"       => $ssid,
                                "mcast_rate"    => 18000,
                                "disabled"      => 0,
                                "mesh_ttl"      => 1,
                                "mesh_fwding"   => 0,
                                "encryption"    => 'psk2/aes',
                                "key"           => $encryption_key
                            )
                        ));
            }    
        }
        //--END MESH Part--

        $start_number = 2;

        //Check if we need to add this wireless VAP
        foreach($ent_mesh->mesh_entries as $me){
        
            $to_all     = false;
            $if_name    = $this->_number_to_word($start_number);
            $entry_id   = $me->id;
            $start_number++;
            if($me->apply_to_all == 1){

                //Check if it is assigned to an exit point
                foreach($entry_point_data as $epd){
                  //  print_r($epd);
                    if(($epd['entry_id'] == $entry_id)&&
                    (($me->frequency_band == 'two')||($me->frequency_band == 'both')) //FIXME Here we assume a single radio will be 2.4G
                    ){ //We found our man :-)
                                  
                        $base_array = array(
                            "device"        => "radio0",
                            "ifname"        => "$if_name"."0",
                            "mode"          => "ap",
                            "network"       => $epd['network'],
                            "encryption"    => $me->encryption,
                            "ssid"          => $me->name,
                            "key"           => $me->special_key,
                            "hidden"        => $me->hidden,
                            "isolate"       => $me->isolate,
                            "auth_server"   => $me->auth_server,
                            "auth_secret"   => $me->auth_secret
                        );
                        
                        if($me->chk_maxassoc){
                            $base_array['maxassoc'] = $me->maxassoc;
                        }
                        
                        if($me->encryption == 'wpa2'){
                             $base_array['nasid'] = $me->nasid;
                        }
                                           
                        if($me->accounting){
                            $base_array['acct_server']	= $me->auth_server;
                            $base_array['acct_secret']	= $me->auth_secret;
                        }
                        
                        if($me->macfilter != 'disable'){
                            $base_array['macfilter']    = $me->macfilter;
                            $mac_list                   = $this->_find_mac_list($me->permanent_user_id);
                            if(count($mac_list)>0){
                                $base_array['maclist'] = implode(" ",$mac_list);
                            }
                        }
                    
                        array_push( $wireless,
                            array(
                                "wifi-iface"=> "$if_name",
                                "options"   => $base_array
                        ));    
                        break;
                    }
                }
            }else{
                //Check if this entry point is statically attached to the node
               // print_r($mesh['Node']);
                foreach($ent_mesh->nodes as $node){
                    if($node->id == $this->NodeId){   //We have our node
                        foreach($node->node_mesh_entries as $nme){
                            if($nme->mesh_entry_id == $entry_id){
                                //Check if it is assigned to an exit point
                                foreach($entry_point_data as $epd){
                                    //We have a hit; we have to  add this entry                                   
                                    if(($epd['entry_id'] == $entry_id)&&
                                        (($me->frequency_band == 'two')||($me->frequency_band == 'both')) //FIXME Here we assume a 2.4G
                                    ){ //We found our man :-)
                                    
                                    
                                        $base_array = array(
                                            "device"        => "radio0",
                                            "ifname"        => "$if_name"."0",
                                            "mode"          => "ap",
                                            "network"       => $epd['network'],
                                            "encryption"    => $me->encryption,
                                            "ssid"          => $me->name,
                                            "key"           => $me->special_key,
                                            "hidden"        => $me->hidden,
                                            "isolate"       => $me->isolate,
                                            "auth_server"   => $me->auth_server,
                                            "auth_secret"   => $me->auth_secret
                                        );
                                        
                                        if($me->chk_maxassoc){
                                            $base_array['maxassoc'] = $me->maxassoc;
                                        }
                                        
                                        if($me->encryption == 'wpa2'){
                                             $base_array['nasid'] = $me->nasid;
                                        }
                                        
                                        if($me->accounting){
                                            $base_array['acct_server']	= $me->auth_server;
                                            $base_array['acct_secret']	= $me->auth_secret;
                                        }
                                        
                                       if($me->macfilter != 'disable'){
                                            $base_array['macfilter']    = $me->macfilter;
                                            $mac_list                   = $this->_find_mac_list($me->permanent_user_id);
                                            if(count($mac_list)>0){
                                                $base_array['maclist'] = implode(" ",$mac_list);
                                            }
                                        } 
                                    
                                        array_push( $wireless,
                                            array(
                                                "wifi-iface"=> "$if_name",
                                                "options"   => $base_array
                                        ));    
                                               
                                        break;
                                    }
                                }
                            }
                        }
                        break;
                    }
                }
            }
        }
        
        if($include_config){
            //Move to the end so that the first 'real' SSID will have the vendor's MAC
            //Add the hidden config VAP
            $one = $this->_number_to_word(1);
            array_push( $wireless,
                    array(
                        "wifi-iface"    => "$one",
                        "options"   => array(
                            "device"        => "radio0",
                            "ifname"        => "$one"."0",
                            "mode"          => "ap",
                            "encryption"    => "psk-mixed",
                            "network"       => $one,
                            "ssid"          => "meshdesk_config",
                            "key"           => $client_key,
                            "hidden"        => "1"
                       )
                    ));
        }   
       // print_r($wireless);
        return $wireless;
    }
    
    private function _build_dual_radio_wireless($ent_mesh,$entry_point_data){

        $wireless = [];
        
		if($ent_mesh->node_setting !== null && $ent_mesh->node_setting->client_key !='') {        
            $client_key = $ent_mesh->node_setting->client_key;
        }else{
            Configure::load('MESHdesk');
		    $client_key = Configure::read('common_node_settings.client_key');
        }

        //Get the channel that the mesh needs to be on
        
        //Get the channel
        if($ent_mesh->node_setting !== null && $ent_mesh->node_setting->two_chan!='') {        
            $mesh_channel_two   = $ent_mesh->node_setting->two_chan;
        }else{
            Configure::load('MESHdesk');
		    $mesh_channel_two   = Configure::read('common_node_settings.two_chan');
        }
        
        if($ent_mesh->node_setting !== null && $ent_mesh->node_setting->five_chan !='') {        
            $mesh_channel_five   = $ent_mesh->node_setting->five_chan;
        }else{
            Configure::load('MESHdesk');
		    $mesh_channel_five   = Configure::read('common_node_settings.five_chan');
        }
        

        //Get the country setting
        if($ent_mesh->node_setting !== null && $ent_mesh->node_setting->country != ''){
            $country  = $ent_mesh->node_setting->country;
		}else{
			Configure::load('MESHdesk');
			$data       = Configure::read('common_node_settings'); //Read the defaults
            $country    = $data['country'];
		}

		//===== RADIO ZERO====
		//Check which of the two is active
		if(!($this->EntNode->radio0_enable)){
			$r0_disabled = '1';
		}else{
			$r0_disabled = '0';
		}

		//-Determine the channel-
		if(!($this->EntNode->radio0_mesh)){ //No mesh - use manual channel
			if($this->EntNode->radio0_band == '24'){
			    if($this->EntNode->radio0_two_chan == 0){
			        $r0_channel = 'auto';
			    }else{
			        $r0_channel =  intval($this->EntNode->radio0_two_chan);
			    }	
			}else{
				$r0_channel =  intval($this->EntNode->radio0_five_chan);
			}
		}else{
			if($this->EntNode->radio0_band == '24'){
				$r0_channel =  intval($mesh_channel_two);
			}else{
				$r0_channel =  intval($mesh_channel_five);
			} 
		}
	
		//Get the hwmode for radio '0';
		$q_e = $this->{'Hardwares'}->find()
		    ->where(['Hardwares.fw_id' => $this->Hardware,'Hardwares.for_mesh' => true])
		    ->contain(['HardwareRadios'])
		    ->first();
		
	    if($q_e){       
	        foreach($q_e->hardware_radios as $hr){
	            if($hr->radio_number == 0){
	                if($hr->hwmode == '11a_ac'){
	                    $r0_hwmode = '11a';
	                }else{
	                    $r0_hwmode = $hr->hwmode;
	                }
	                break; //Break the loop we found our guy
	            }
	        } 
	    }

        $radio_zero_capab = array();
        //Somehow the read thing reads double..
        $allready_there = array();
        foreach($this->RadioSettings[0]['radio0_ht_capab'] as $c){
            if($c !== ''){
                if(!in_array($c,$allready_there)){
                    array_push($allready_there,$c);
                    array_push($radio_zero_capab,array('name'    => 'ht_capab', 'value'  => $c));
                }
            }
        } 
        
        $r0_htmode  = $this->RadioSettings[0]['radio0_htmode'];
        $r0_txpower = $this->RadioSettings[0]['radio0_txpower'];
        
        $options_0  = [
            'channel'       => $r0_channel,
            'disabled'      => $r0_disabled,
            'hwmode'        => $r0_hwmode,
            'htmode'        => $r0_htmode, 
            'country'       => $country,
            'txpower'       => intval($r0_txpower)
        ];
        
        //For now we have these binary options that we obity if not specified
        if(isset($this->RadioSettings[0]['radio0_include_distance'])){
            $options_0['distance'] = intval($this->RadioSettings[0]['radio0_distance']);
        }
        
        if(isset($this->RadioSettings[0]['radio0_include_beacon_int'])){
            $options_0['beacon_int'] = intval($this->RadioSettings[0]['radio0_beacon_int']);
        }
        //END Bin options
        
		array_push( $wireless,
            array(
                "wifi-device"   => "radio0",
                "options"       => $options_0,
                'lists'         => $radio_zero_capab
               
      	));

		//===== RADIO ONE====
		if(!($this->EntNode->radio1_enable)){
			$r1_disabled = '1';
		}else{
			$r1_disabled = '0';
		}

		//-Determine the channel-
		if(!($this->EntNode->radio1_mesh)){ //No mesh - use manual channel     
			if($this->EntNode->radio1_band == 24){ 
			    if($this->EntNode->radio1_two_chan == 0){
			        $r1_channel = 'auto';
			    }else{
			        $r1_channel =  intval($this->EntNode->radio1_two_chan);
			    }
			}else{
				$r1_channel =  intval($this->EntNode->radio1_five_chan);
			}
		}else{
			if($this->EntNode->radio1_band == '24'){
				$r1_channel =  intval($mesh_channel_two);
			}else{
				$r1_channel =  intval($mesh_channel_five);
			} 
		}

		//Get the hwmode for radio '1';
		$q_e = $this->{'Hardwares'}->find()
		    ->where(['Hardwares.fw_id' => $this->Hardware,'Hardwares.for_mesh' => true])
		    ->contain(['HardwareRadios'])
		    ->first();
		
	    if($q_e){       
	        foreach($q_e->hardware_radios as $hr){
	            if($hr->radio_number == 1){
	                if($hr->hwmode == '11a_ac'){
	                    $r1_hwmode = '11a';
	                }else{
	                    $r1_hwmode = $hr->hwmode;
	                }
	                break; //Break the loop we found our guy
	            }
	        } 
	    }
		

        $radio_one_capab = array();
        //Somehow the read thing reads double..
        $allready_there = array();
        foreach($this->RadioSettings[1]['radio1_ht_capab'] as $c){
            if($c !== ''){
                if(!in_array($c,$allready_there)){
                    array_push($allready_there,$c);
                    array_push($radio_one_capab,array('name'    => 'ht_capab', 'value'  => $c));
                }
            }
        }
        
        $r1_htmode     = $this->RadioSettings[1]['radio1_htmode'];
        $r1_txpower    = $this->RadioSettings[1]['radio1_txpower'];
        
        $options_1  = [
            'channel'       => $r1_channel,
            'disabled'      => $r1_disabled,
            'hwmode'        => $r1_hwmode,
            'htmode'        => $r1_htmode, 
            'country'       => $country,
            'txpower'       => intval($r1_txpower)
        ];
        
        //For now we have these binary options that we obity if not specified
        if(isset($this->RadioSettings[1]['radio1_include_distance'])){
            $options_1['distance'] = intval($this->RadioSettings[1]['radio1_distance']);
        }
        
        if(isset($this->RadioSettings[1]['radio1_include_beacon_int'])){
            $options_1['beacon_int'] = intval($this->RadioSettings[1]['radio1_beacon_int']);
        }
        //END Bin options
        

		array_push( $wireless,
            array(
                "wifi-device"   => "radio1",
                "options"       => $options_1,
                'lists'         => $radio_one_capab
      	));

		//_____ MESH _______
        //Get the mesh's BSSID and SSID
        $bssid      = $ent_mesh->bssid;
        $ssid       = $ent_mesh->ssid;
        
        //Get the connection type (IBSS or mesh_point);
        if($ent_mesh->mesh_setting != null){  
            $connectivity   = $ent_mesh->mesh_setting->connectivity;
            $encryption     = $ent_mesh->mesh_setting->encryption;
            $encryption_key = $ent_mesh->mesh_setting->encryption_key;
        }else{
            Configure::load('MESHdesk');
		    $connectivity   = Configure::read('mesh_settings.connectivity');
		    $encryption     = Configure::read('mesh_settings.encryption');
            $encryption_key = Configure::read('mesh_settings.encryption_key');
        }

		if(($this->EntNode->radio0_enable == 1)&&($this->EntNode->radio0_mesh == 1)){
		    $zero = $this->_number_to_word(0);	    
		    if($connectivity == 'IBSS'){
                array_push( $wireless,
                        array(
                            "wifi-iface"   => "$zero",
                            "options"       => array(
                                "device"        => "radio0",
                                "ifname"        => "mesh0",
//                                "macaddr"       => $this->_create_mac($this->Mac,'aa'),
                                "network"       => "mesh",
                                "mode"          => "adhoc",
                                "ssid"          => $ssid,
                                "bssid"         => $bssid
                            )
                        ));
            }
         
            if(($connectivity == 'mesh_point')&&(!$encryption)){
                array_push( $wireless,
                        array(
                            "wifi-iface"   => "$zero",
                            "options"       => array(
                                "device"        => "radio0",
                                "ifname"        => "mesh0",
//                                "macaddr"       => $this->_create_mac($this->Mac,'aa'),
                                "network"       => "mesh",
                                "mode"          => "mesh",
                                "mesh_id"       => $ssid,
                                "mcast_rate"    => 18000,
                                "disabled"      => 0,
                                "mesh_ttl"      => 1,
                                "mesh_fwding"   => 0,
                                "encryption"    => 'none'
                            )
                        ));
            }
            
            if(($connectivity == 'mesh_point')&&($encryption)){
                array_push( $wireless,
                        array(
                            "wifi-iface"   => "$zero",
                            "options"       => array(
                                "device"        => "radio0",
                                "ifname"        => "mesh0",
//                                "macaddr"       => $this->_create_mac($this->Mac,'aa'),
                                "network"       => "mesh",
                                "mode"          => "mesh",
                                "mesh_id"       => $ssid,
                                "mcast_rate"    => 18000,
                                "disabled"      => 0,
                                "mesh_ttl"      => 1,
                                "mesh_fwding"   => 0,
                                "encryption"    => 'psk2/aes',
                                "key"           => $encryption_key
                            )
                        ));
            }
		}

		if(($this->EntNode->radio1_enable == 1)&&($this->EntNode->radio1_mesh == 1)){
		    $zero = $this->_number_to_word(0);
			$zero = $zero."_1";
			if($connectivity == 'IBSS'){
                array_push( $wireless,
                        array(
                            "wifi-iface"   => "$zero",
                            "options"       => array(
                                "device"        => "radio1",
                                "ifname"        => "mesh1",
                               // "macaddr"       => $this->_create_mac($this->Mac,'bb'),
                                "network"       => "mesh",
                                "mode"          => "adhoc",
                                "ssid"          => $ssid,
                                "bssid"         => $bssid
                            )
                        ));
            }
         
            if(($connectivity == 'mesh_point')&&(!$encryption)){
                array_push( $wireless,
                        array(
                            "wifi-iface"   => "$zero",
                            "options"       => array(
                                "device"        => "radio1",
                                "ifname"        => "mesh1",
                               // "macaddr"       => $this->_create_mac($this->Mac,'bb'),
                                "network"       => "mesh",
                                "mode"          => "mesh",
                                "mesh_id"       => $ssid,
                                "mcast_rate"    => 18000,
                                "disabled"      => 0,
                                "mesh_ttl"      => 1,
                                "mesh_fwding"   => 0,
                                "encryption"    => 'none'
                            )
                        ));
            }
            
            if(($connectivity == 'mesh_point')&&($encryption)){
                array_push( $wireless,
                        array(
                            "wifi-iface"   => "$zero",
                            "options"       => array(
                                "device"        => "radio1",
                                "ifname"        => "mesh1",
                               // "macaddr"       => $this->_create_mac($this->Mac,'bb'),
                                "network"       => "mesh",
                                "mode"          => "mesh",
                                "mesh_id"       => $ssid,
                                "mcast_rate"    => 18000,
                                "disabled"      => 0,
                                "mesh_ttl"      => 1,
                                "mesh_fwding"   => 0,
                                "encryption"    => 'psk2/aes',
                                "key"           => $encryption_key
                            )
                        ));
            }
		}

        $start_number = 2;

		//____ ENTRY POINTS ____

        //Check if we need to add this wireless VAP
        foreach($ent_mesh->mesh_entries as $me){
            $to_all     = false;
            $if_name    = $this->_number_to_word($start_number);
            $entry_id   = $me->id;
            $start_number++;
            if($me->apply_to_all == 1){

                //Check if it is assigned to an exit point
                foreach($entry_point_data as $epd){
                    if($epd['entry_id'] == $entry_id){ //We found our man :-)

						if( ($this->EntNode->radio0_enable == 1)&&($this->EntNode->radio0_entry == 1)){
						    //This is splitting 2.4 and 5G
							$radio_band = $this->EntNode->radio0_band;
							if(
							(($radio_band == '5')&&(($me->frequency_band == 'five')||($me->frequency_band == 'both')))||
							(($radio_band == '24')&&(($me->frequency_band == 'two')||($me->frequency_band == 'both')))
							){	
																    
						        $base_array = array(
                                    "device"        => "radio0",
                                    "ifname"        => "$if_name"."0",
                                    "mode"          => "ap",
                                    "network"       => $epd['network'],
                                    "encryption"    => $me->encryption,
                                    "ssid"          => $me->name,
                                    "key"           => $me->special_key,
                                    "hidden"        => $me->hidden,
                                    "isolate"       => $me->isolate,
                                    "auth_server"   => $me->auth_server,
                                    "auth_secret"   => $me->auth_secret
                                );
                                
                                if($me->chk_maxassoc){
                                    $base_array['maxassoc'] = $me->maxassoc;
                                }
                                
                                if($me->encryption == 'wpa2'){
                                     $base_array['nasid'] = $me->nasid;
                                }
                                
                                if($me->accounting){
                                    $base_array['acct_server']	= $me->auth_server;
                                    $base_array['acct_secret']	= $me->auth_secret;
                                }
                                
                                if($me->macfilter != 'disable'){
                                    $base_array['macfilter']    = $me->macfilter;
                                    //Replace later
                                    $pu_id      = $me->permanent_user_id;
                                    $ent_dev    = $this->Devices->find()->where(['Devices.permanent_user_id' => $pu_id])->all();
                                    $mac_list   = [];
                                    foreach($ent_dev as $device){
                                        $mac = $device->name;
                                        $mac = str_replace('-',':',$mac);
                                        array_push($mac_list,$mac);
                                    }
                                    if(count($mac_list)>0){
                                        $base_array['maclist'] = implode(" ",$mac_list);
                                    }
                                }
                                
                                if($me->macfilter != 'disable'){
                                    $base_array['macfilter']    = $me->macfilter;
                                    $mac_list                   = $this->_find_mac_list($me->permanent_user_id);
                                    $mac_list                   = [];
                                    if(count($mac_list)>0){
                                        $base_array['maclist'] = implode(" ",$mac_list);
                                    }
                                }
                                
                            
                                array_push( $wireless,
                                    array(
                                        "wifi-iface"=> "$if_name",
                                        "options"   => $base_array
                                ));
                            
                            }   						  
						}

						if(($this->EntNode->radio1_enable == 1)&&($this->EntNode->radio1_entry == 1)){
						
						    //This is splitting 2.4 and 5G
						    $radio_band = $this->EntNode->radio1_band;
							if(
							(($radio_band == '5')&&(($me->frequency_band == 'five')||($me->frequency_band == 'both')))||
							(($radio_band == '24')&&(($me->frequency_band == 'two')||($me->frequency_band == 'both')))
							){	

						        $base_array = array(
                                    "device"        => "radio1",
                                    "ifname"        => "$if_name"."1",
                                    "mode"          => "ap",
                                    "network"       => $epd['network'],
                                    "encryption"    => $me->encryption,
                                    "ssid"          => $me->name,
                                    "key"           => $me->special_key,
                                    "hidden"        => $me->hidden,
                                    "isolate"       => $me->isolate,
                                    "auth_server"   => $me->auth_server,
                                    "auth_secret"   => $me->auth_secret
                                );
                                
                                if($me->chk_maxassoc){
                                    $base_array['maxassoc'] = $me->maxassoc;
                                }
                                
                                if($me->encryption == 'wpa2'){
                                     $base_array['nasid'] = $me->nasid;
                                }
                                
                                if($me->accounting){
                                    $base_array['acct_server']	= $me->auth_server;
                                    $base_array['acct_secret']	= $me->auth_secret;
                                }
                                
                                if($me->macfilter != 'disable'){
                                    $base_array['macfilter']    = $me->macfilter;
                                    $mac_list                   = $this->_find_mac_list($me->permanent_user_id);
                                    $mac_list                   = [];
                                    if(count($mac_list)>0){
                                        $base_array['maclist'] = implode(" ",$mac_list);
                                    }
                                }                                
                                array_push( $wireless,
                                    array(
                                        "wifi-iface"=> "$if_name"."_1",
                                        "options"   => $base_array
                                ));
                                
                            }       
						}
                        break;
                    }
                }
            }else{
                //Check if this entry point is statically attached to the node
                //print_r($ent_mesh);
                foreach($ent_mesh->nodes as $node){
                    if($node->id == $this->NodeId){   //We have our node
                        foreach($node->node_mesh_entries as $nme){
                            if($nme->mesh_entry_id == $entry_id){
                                //Check if it is assigned to an exit point
                                foreach($entry_point_data as $epd){                        
                                    //We have a hit; we have to  add this entry
                                    if($epd['entry_id'] == $entry_id){ //We found our man :-)
                                        //print_r($node);
										if(($this->EntNode->radio0_enable == 1)&&($this->EntNode->radio0_entry == 1)){

										    $radio_band = $this->EntNode->radio0_band;
							                if(
							                (($radio_band == '5')&&(($me->frequency_band == 'five')||($me->frequency_band == 'both')))||
							                (($radio_band == '24')&&(($me->frequency_band == 'two')||($me->frequency_band == 'both')))
							                ){
										        $base_array = array(
                                                    "device"        => "radio0",
                                                    "ifname"        => "$if_name"."0",
                                                    "mode"          => "ap",
                                                    "network"       => $epd['network'],
                                                    "encryption"    => $me->encryption,
                                                    "ssid"          => $me->name,
                                                    "key"           => $me->special_key,
                                                    "hidden"        => $me->hidden,
                                                    "isolate"       => $me->isolate,
                                                    "auth_server"   => $me->auth_server,
                                                    "auth_secret"   => $me->auth_secret
                                                );
                                                
                                                if($me->chk_maxassoc){
                                                    $base_array['maxassoc'] = $me->maxassoc;
                                                }
                                                
                                                if($me->encryption == 'wpa2'){
                                                     $base_array['nasid'] = $me->nasid;
                                                }
                                                
                                                if($me->accounting){
                                                    $base_array['acct_server']	= $me->auth_server;
                                                    $base_array['acct_secret']	= $me->auth_secret;
                                                }
                                                
                                                if($me->macfilter != 'disable'){
                                                    $mac_list                   = [];
                                                    $base_array['macfilter']    = $me->macfilter;
                                                    $mac_list                   = $this->_find_mac_list($me->permanent_user_id);
                                                    
                                                    if(count($mac_list)>0){
                                                        $base_array['maclist'] = implode(" ",$mac_list);
                                                    }
                                                }
                                                
                                            
                                                array_push( $wireless,
                                                    array(
                                                        "wifi-iface"=> "$if_name",
                                                        "options"   => $base_array
                                                ));
                                                
                                            }					   
										}

										if(($this->EntNode->radio1_enable == 1)&&($this->EntNode->radio1_entry == 1)){
										
										    
										    $radio_band = $this->EntNode->radio1_band;
							                if(
							                (($radio_band == '5')&&(($me->frequency_band == 'five')||($me->frequency_band == 'both')))||
							                (($radio_band == '24')&&(($me->frequency_band == 'two')||($me->frequency_band == 'both')))
							                ){
										
										        $base_array = array(
                                                    "device"        => "radio1",
                                                    "ifname"        => "$if_name"."1",
                                                    "mode"          => "ap",
                                                    "network"       => $epd['network'],
                                                    "encryption"    => $me->encryption,
                                                    "ssid"          => $me->name,
                                                    "key"           => $me->special_key,
                                                    "hidden"        => $me->hidden,
                                                    "isolate"       => $me->isolate,
                                                    "auth_server"   => $me->auth_server,
                                                    "auth_secret"   => $me->auth_secret
                                                );
                                                
                                                if($me->chk_maxassoc){
                                                    $base_array['maxassoc'] = $me->maxassoc;
                                                }
                                                
                                                if($me->encryption == 'wpa2'){
                                                     $base_array['nasid'] = $me->nasid;
                                                }
                                                
                                                if($me->accounting){
                                                    $base_array['acct_server']	= $me->auth_server;
                                                    $base_array['acct_secret']	= $me->auth_secret;
                                                }
                                                
                                                if($me->macfilter != 'disable'){
                                                    $base_array['macfilter']    = $me->macfilter;
                                                    //Replace later
                                                    $pu_id      = $me->permanent_user_id;
                                                    $ent_dev    = $this->Devices->find()->where(['Devices.permanent_user_id' => $pu_id])->all();
                                                    $mac_list   = [];
                                                    foreach($ent_dev as $device){
                                                        $mac = $device->name;
                                                        $mac = str_replace('-',':',$mac);
                                                        array_push($mac_list,$mac);
                                                    }
                                                    if(count($mac_list)>0){
                                                        $base_array['maclist'] = implode(" ",$mac_list);
                                                    }
                                                }
										       										    
		                                        array_push( $wireless,
		                                            array(
		                                                "wifi-iface"    => "$if_name"."_1",
		                                                "options"       => $base_array
		                                        ));
		                                    }              
										}
                                        break;
                                    }
                                }
                            }
                        }
                        break;
                    }
                }
            }
        }
        
        //== Put it at the end so that the first 'Real' SSID takes the MAC from the RADIO to echo the vendor
        //____ HIDDEN VAP ______

      	if(($this->EntNode->radio0_enable == 1)&&($this->EntNode->radio0_mesh == 1)){
		    $one = $this->_number_to_word(1);
		    
		    //The ATH10K does not like this VAP so we try to avoid it on 5G
		    //Only if the other radio is enabled but without mesh
		    if(
		        ($this->EntNode->radio0_band == 5)&&
		        ($this->EntNode->radio1_enable == 1)&&
		        ($this->EntNode->radio1_mesh !== 1)
		    ){ 
                array_push( $wireless,
                    array(
                        "wifi-iface"    => "$one",
                        "options"   => array(
                            "device"        => "radio1",
                            "ifname"        => "$one"."1",
                            "mode"          => "ap",
                            "encryption"    => "psk-mixed",
                            "network"       => $one,
                            "ssid"          => "meshdesk_config",
                            "key"           => $client_key,
                            "hidden"        => "1"
                       )
                ));
		    
		    }else{		        
	            array_push( $wireless,
	                array(
	                    "wifi-iface"    => "$one",
	                    "options"   => array(
	                        "device"        => "radio0",
	                        "ifname"        => "$one"."0",
	                        "mode"          => "ap",
	                        "encryption"    => "psk-mixed",
	                        "network"       => $one,
	                        "ssid"          => "meshdesk_config",
	                        "key"           => $client_key,
	                        "hidden"        => "1"
	                   )
	                ));
		    }    
		}

		if(($this->EntNode->radio1_enable == 1)&&($this->EntNode->radio1_mesh == 1)){
		    $one = $this->_number_to_word(1);
		    
		    //The ATH10K does not like this VAP so we try to avoid it on 5G
		    //Only if the other radio is enabled but without mesh
		    if(
		        ($this->EntNode->radio1_band == 5)&&
		        ($this->EntNode->radio0_enable == 1)&&
		        ($this->EntNode->radio0_mesh !== 1)
		    ){
		    
		        array_push( $wireless,
	                [
	                    "wifi-iface"    => "$one"."_1",
	                    "options"   => [
	                        "device"        => "radio0",
	                        "ifname"        => "$one"."0",
	                        "mode"          => "ap",
	                        "encryption"    => "psk-mixed",
	                        "network"       => $one,
	                        "ssid"          => "meshdesk_config",
	                        "key"           => $client_key,
	                        "hidden"        => "1"
	                   ]
	            ]);
		       
		    }else{
		        array_push( $wireless,
	                [
	                    "wifi-iface"    => "$one"."_1",
	                    "options"   => [
	                        "device"        => "radio1",
	                        "ifname"        => "$one"."1",
	                        "mode"          => "ap",
	                        "encryption"    => "psk-mixed",
	                        "network"       => $one,
	                        "ssid"          => "meshdesk_config",
	                        "key"           => $client_key,
	                        "hidden"        => "1"
	                   ]
	            ]);
		    }
		}
        
        return $wireless;
    }
    
     private function _build_openvpn_bridges($openvpn_list){
        $openvpn_bridges = [];
        foreach($openvpn_list as $o){
        
            $br                 = [];
            $br['interface']    = $o['interface'];
            $br['up']           = "mesh_".$this->Mac."\n".md5("mesh_".$this->Mac)."\n";
            $br['ca']           = $o['ca_crt'];
            $br['vpn_gateway_address'] = $o['vpn_gateway_address'];
            $br['vpn_client_id'] = $o['vpn_client_id'];
            
            Configure::load('OpenvpnClientPresets');
            $config_file    = Configure::read('OpenvpnClientPresets.'.$o['config_preset']); //Read the defaults

            $config_file['remote']  = $o['ip_address'].' '.$o['port'];
            $config_file['up']      = '"/etc/openvpn/up.sh br-'.$o['interface'].'"';
            $config_file['proto']   = $o['protocol'];
            $config_file['ca']      = '/etc/openvpn/'.$o['interface'].'_ca.crt';
            $config_file['auth_user_pass'] = '/etc/openvpn/'.$o['interface'].'_up';  
            $br['config_file']      = $config_file;
            array_push($openvpn_bridges,$br);
        }
        return $openvpn_bridges;
    }


    private function _eth_br_for($hw){
		$return_val = 'eth0'; //some default
		
		$q_e = $this->{'Hardwares'}->find()->where(['Hardwares.fw_id' => $hw, 'Hardwares.for_mesh' => true])->first();
		if($q_e){
		    $return_val = $q_e->wan;   
		}
		return $return_val;
	}
	
	private function _number_to_word($number) {
   
   
        $dictionary  = [
            0                   => 'zero',
            1                   => 'one',
            2                   => 'two',
            3                   => 'three',
            4                   => 'four',
            5                   => 'five',
            6                   => 'six',
            7                   => 'seven',
            8                   => 'eight',
            9                   => 'nine',
            10                  => 'ten',
            11                  => 'eleven',
            12                  => 'twelve',
            13                  => 'thirteen',
            14                  => 'fourteen',
            15                  => 'fifteen',
            16                  => 'sixteen',
            17                  => 'seventeen',
            18                  => 'eighteen',
            19                  => 'nineteen',
            20                  => 'twenty'
        ];

        return($dictionary[$number]);
    } 
    
    private function _find_mac_list($pu_uid){
    
        $pu_id      = $me->permanent_user_id;
        $ent_dev    = $this->Devices->find()->where(['Devices.permanent_user_id' => $pu_id])->all();
        $mac_list   = [];
        foreach($ent_dev as $device){
            $mac = $device->name;
            $mac = str_replace('-',':',$mac);
            array_push($mac_list,$mac);
        }    
        return $mac_list;
    }
       
}
