<?php

namespace App\Controller;

use Cake\Core\Configure;
use Exception;
use GeoIp2\Database\Reader;

class ApProfilesController extends AppController {

    public $main_model          = 'ApProfiles';
    public $base             = "Access Providers/Controllers/ApProfiles/";

    public function initialize()
    {
        parent::initialize();
        $this->loadModel($this->main_model);
        $this->loadModel('Users');
        $this->loadModel('DynamicClients');
        $this->loadModel('DynamicPairs');
        $this->loadModel('DynamicClientRealms');
        $this->loadModel('Realms');
        $this->loadModel('OpenvpnServers');
        $this->loadModel('OpenvpnServerClients');
        $this->loadModel('ApProfileNotes');
        $this->loadModel('ApProfileEntries');
        
        //New change May2021
        $this->loadModel('Networks');
        $this->loadModel('Sites');
        $this->loadModel('Clouds');
        
        $this->loadModel('Hardwares');
        $this->loadModel('Timezones');

        $this->loadComponent('Aa');
        $this->loadComponent('GridButtons'); 
        $this->loadComponent('GridFilter');
        $this->loadComponent('TimeCalculations');
        $this->loadComponent('JsonErrors');
        
        $this->loadComponent('Notes', [
            'model'     => 'ApProfileNotes',
            'condition' => 'ap_profile_id'
        ]); 
        
        $this->loadComponent('CommonQuery', [ //Very important to specify the Model
            'model' => 'ApProfiles'
        ]);
    }

    //____ BASIC CRUD Manager ________
    public function index(){

        //__ Authentication + Authorization __
        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }
        $user_id    = $user['id'];

        $query = $this->{$this->main_model}->find();

         $this->CommonQuery->build_common_query($query, $user, ['Users','ApProfileNotes' => ['Notes'],'Aps']); //AP QUERY is sort of different in a way

        //===== PAGING (MUST BE LAST) ======
        $limit  = 50;   //Defaults
        $page   = 1;
        $offset = 0;

        if(null !== $this->request->getQuery('limit')){
            $limit  = $this->request->getQuery('limit');
            $page   = $this->request->getQuery('page');
            $offset = $this->request->getQuery('start');
        }

        $query->page($page);
        $query->limit($limit);
        $query->offset($offset);

        $total = $query->count();
        $q_r = $query->all();

        $items      = [];

        foreach($q_r as $i){
            //Create notes flag
            $notes_flag  = false;
            foreach($i->ap_profile_notes as $nn){
                if(! $this->_test_for_private_parent($nn->note, $user)){
                    $notes_flag = true;
                    break;
                }
            }

            $owner_id       = $i->user_id;
            $owner_tree     = $this->_find_parents($owner_id);      
            $action_flags   = $this->Aa->get_action_flags($owner_id, $user);
			$ap_profile_id  = $i->id;

			$now		= time();

			$ap_count 	= 0;
			$aps_up		= 0;
			$aps_down   = 0;
			foreach($i->aps as $ap){
			    //Get the 'dead_after' value
			    $dead_after = $this->_get_dead_after($ap->ap_profile_id);
			
				$l_contact  = $ap->last_contact;
				//===Determine when last did we saw this ap (never / up / down) ====
				$last_timestamp = strtotime($l_contact);
	            if($last_timestamp+$dead_after <= $now){
	                $aps_down++;
	            }else{
					$aps_up++;  
	            }
				$ap_count++;
			}

            array_push($items, [
                'id'                    => $i->id,
                'name'                  => $i->name,
				'available_to_siblings' => $i->available_to_siblings,
                'ap_count'              => $ap_count,
                'aps_up'                => $aps_up,
                'aps_down'              => $aps_down,
                'owner'                 => $owner_tree, 
                'notes'                 => $notes_flag,
                'update'                => $action_flags['update'],
                'delete'                => $action_flags['delete'],
                'view'                  => $action_flags['view'],
            ]);
        }
       
        //___ FINAL PART ___
        $this->set([
            'items' => $items,
            'success' => true,
            'totalCount' => $total,
            '_serialize' => ['items','success','totalCount']
        ]);
    }
    
    
    public function add() {

        //__ Authentication + Authorization __
        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }
        $user_id = $user['id'];

        //Get the creator's id
        $cdata = $this->request->getData();
        if($this->request->getData('user_id') == '0'){ //This is the holder of the token - override '0'
            $cdata['user_id'] = $user_id;
        }

		//Make available to siblings check
        if(null !== $this->request->getData('available_to_siblings')){
            $cdata['available_to_siblings'] = 1;
        }else{
            $cdata['available_to_siblings'] = 0;
        }

        $apProfileEntity = $this->{$this->main_model}->newEntity($cdata);

        if ($this->{$this->main_model}->save($apProfileEntity)) {
            $this->set([
                'success' => true,
                '_serialize' => ['success']
            ]);
        }else {
            $message = 'Error';
            $this->set([
                'errors'    => $this->JsonErrors->entityErros($apProfileEntity, $message),
                'success'   => false,
                'message'   => ['message' => __('Could not create item')],
                '_serialize' => ['errors','success','message']
            ]);
        }
	}

    public function delete() {
		if (!$this->request->is('post')) {
			throw new MethodNotAllowedException();
		}

        //__ Authentication + Authorization __
        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }

        $user_id   = $user['id'];
        $fail_flag = false;

	    if(isset($this->request->data['id'])){   //Single item delete
            $message = "Single item ".$this->request->data['id'];

            //NOTE: we first check of the user_id is the logged in user OR a sibling of them:         
            $entity     = $this->{$this->main_model}->get($this->request->data['id']);   
            $owner_id   = $entity->user_id;
            
            if($owner_id != $user_id){
                if($this->Users->is_sibling_of($user_id,$owner_id)== true){
                    $this->{$this->main_model}->delete($entity);
                }else{
                    $fail_flag = true;
                }
            }else{
                $this->{$this->main_model}->delete($entity);
            }
   
        }else{                          //Assume multiple item delete
            foreach($this->request->data as $d){
                $entity     = $this->{$this->main_model}->get($d['id']);  
                $owner_id   = $entity->user_id;
                if($owner_id != $user_id){
                    if($this->Users->is_sibling_of($user_id,$owner_id) == true){
                        $this->{$this->main_model}->delete($entity);
                    }else{
                        $fail_flag = true;
                    }
                }else{
                    $this->{$this->main_model}->delete($entity);
                }
            }
        }

        if($fail_flag == true){
            $this->set(array(
                'success'   => false,
                'message'   => array('message' => __('Could not delete some items')),
                '_serialize' => array('success','message')
            ));
        }else{
            $this->set(array(
                'success' => true,
                '_serialize' => array('success')
            ));
        }
	}
	
	//====== Notes ===============
    public function noteIndex(){
        //__ Authentication + Authorization __
        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }
        $items = $this->Notes->index($user); 
    }
    
    public function noteAdd(){
        //__ Authentication + Authorization __
        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }   
        $this->Notes->add($user);
    }
    
    public function noteDel(){  
        if (!$this->request->is('post')) {
			throw new MethodNotAllowedException();
		}
        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }
        $this->Notes->del($user);
    }
	
	
	//======= AP Profile entries ============
    public function apProfileEntriesIndex(){

        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }

        $items          = [];
        $total          = 0;
        $ap_profile_id  = $this->request->getQuery('ap_profile_id');
        $q_r = $this->ApProfileEntries->find()->contain(['ApProfileExitApProfileEntries'])->where(['ApProfileEntries.ap_profile_id' => $ap_profile_id])->all();

        foreach($q_r as $m){
            $connected_to_exit = true;   
            if(count($m->ap_profile_exit_ap_profile_entries) == 0){
                $connected_to_exit = false;
            }
   
            array_push($items,array( 
                'id'            => $m->id,
                'ap_profile_id' => $m->ap_profile_id,
                'name'          => $m->name,
                'hidden'        => $m->hidden,
                'isolate'       => $m->isolate,
                'encryption'    => $m->encryption,
                'special_key'   => $m->special_key,
                'auth_server'   => $m->auth_server,
                'auth_secret'   => $m->auth_secret,
                'dynamic_vlan'  => $m->dynamic_vlan,
                'frequency_band'  => $m->frequency_band,
                'connected_to_exit' => $connected_to_exit
            ));
        }
        //___ FINAL PART ___
        $this->set([
            'items' => $items,
            'success' => true,
            'totalCount' => $total,
            '_serialize' => ['items','success','totalCount']
        ]);
    }

    public function apProfileEntryAdd(){
        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }

        $entryEntity = $this->ApProfileEntries->newEntity($this->request->getData());
        if ($this->ApProfileEntries->save($entryEntity)) {
            $id = $entryEntity->id;
            if(null !== $this->request->getData('auto_nasid')){
                $q_r = $this->ApProfiles->find()->where(['id' => $this->request->getData('ap_profile_id')])->first();
                $app_name = $q_r->name;
                $ap_profile_name_underscored = preg_replace('/\s+/', '_', $app_name);
                $ap_profile_name_underscored = strtolower($ap_profile_name_underscored);
                $nasid = $ap_profile_name_underscored.'_apeap_'.$id;
                $entry->saveField("nasid","$nasid");
            } 
            $this->set([
                'success' => true,
                '_serialize' => ['success']
            ]);
        } else {
            $message = 'Error';
            $this->set([
                'errors'    => $this->JsonErrors->entityErros($entryEntity, $message),
                'success'   => false,
                'message'   => ['message' => __('Could not create item')],
                '_serialize' => ['errors','success','message']
            ]);
        }
    }

    public function apProfileEntryEdit(){

		$user = $this->_ap_right_check();
        if(!$user){
            return;
        }

        $cdata = $this->request->getData();

        if ($this->request->is('post')) {
        
             //Check if we have to auto gen this nasid
            if(null !== $this->request->getData('auto_nasid')){
                $q_r = $this->ApProfiles->find()->where(['id' => $this->request->getData('ap_profile_id')])->first();
                $app_name = $q_r->name;
                $ap_profile_name_underscored = preg_replace('/\s+/', '_', $app_name);
                $ap_profile_name_underscored = strtolower($ap_profile_name_underscored);
                $cdata['nasid'] = $ap_profile_name_underscored.'_apeap_'.$this->request->getData('id');
            }

            $check_items = ['hidden','isolate','apply_to_all','chk_maxassoc','accounting','auto_nasid'];
            foreach($check_items as $i){
                if(isset($cdata[$i])){
                    $cdata[$i] = 1;
                }else{
                    $cdata[$i] = 0;
                }
            }

            $entryEntity = $this->ApProfileEntries->newEntity($this->request->getData());
            // If the form data can be validated and saved...
            if ($this->ApProfileEntries->save($entryEntity)) {
                   $this->set([
                    'success' => true,
                    '_serialize' => ['success']
                ]);
            }
        } 
    }

    public function apProfileSsidsView(){  
        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }       
        $items          = [];      
        $ap_id          = $this->request->getQuery('ap_id');
        
        $this->loadModel('Aps');
        
        $q_ap = $this->{'Aps'}->find()->where(['id' => $ap_id])->first();
        if($q_ap){
            $ap_profile_id = $q_ap->ap_profile_id;
        }
        
        $this->loadModel('ApProfileEntries');
        $query  = $this->{'ApProfileEntries'}->find()
                    ->where(['ApProfileEntries.ap_profile_id' => $ap_profile_id])
                    ->order(['ApProfileEntries.name']);                   
        $q_r      = $query->all();
        array_push($items,['id' => -1,'name'=> '** ALL SSIDs **']);
        foreach ($q_r as $i) {
            array_push($items,['id' => $i->id,'name'=> $i->name]);
        }
        
        $this->set(array(
            'items'     => $items,
            'success'   => true,
            '_serialize'=> array('success', 'items')
        ));   
    }

    public function apProfileEntryView(){

        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }

        $id    = $this->request->getQuery('entry_id');
        $q_r   = $this->ApProfileEntries->find()->where(['id' => $id])->first();
        
        if($q_r->macfilter != 'disable'){
            $this->loadModel('PermanentUsers');
            $q = $this->PermanentUsers->find()->where(['id' => $q_r->permanent_user_id])->first();
            if($q){
                $q_r->username = $q->username;
            }else{
                $q_r->username = "!!!User Missing!!!";
            }
        }

        $this->set([
            'data'     => $q_r,
            'success'   => true,
            '_serialize'=> ['success', 'data']
        ]);
    }

    public function apProfileEntryDelete(){

       if (!$this->request->is('post')) {
			throw new MethodNotAllowedException();
		}

        //__ Authentication + Authorization __
        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }

        $user_id    = $user['id'];
        $fail_flag  = false;

	    if(isset($this->request->data['id'])){   //Single item delete
            $message = "Single item ".$this->request->data['id'];
            $ent_entry       = $this->{'ApProfileEntries'}->find()->where(['ApProfileEntries.id' => $this->request->data['id']])->first();
            if($ent_entry){
                $this->{'ApProfileEntries'}->delete($ent_entry);        
            }        
        }else{                          //Assume multiple item delete
            foreach($this->request->data as $d){
                $ent_entry       = $this->{'ApProfileEntries'}->find()->where(['ApProfileEntries.id' => $d['id']])->first();
                if($ent_entry){
                    $this->{'ApProfileEntries'}->delete($ent_entry);        
                } 
            }
        }  
        $this->set([
            'success' => true,
            '_serialize' => ['success']
        ]);
    }
    
    //======= AP Profile exits ============
    public function apProfileExitsIndex(){
        $this->loadModel('ApProfileExits');
//        $this->loadModel('ApProfileExitApProfileEntries');

        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }

        $items          = [];
        $total          = 0;
        $ap_profile_id  = $this->request->getQuery('ap_profile_id');       
        $add_no_exit    = $this->request->getQuery('add_no_exit');
        
        if($add_no_exit == 'true'){
        
            array_push($items, [
                'id'            => 0,
                'ap_profile_id' => $ap_profile_id,
                'type'          => 'no_bridge',
                'connects_with' => []
            ]);       
        }       
        
       // print_r($q_r);
        $q_r = $this->ApProfileExits->find()
            ->contain(['ApProfileExitApProfileEntries.ApProfileEntries'])
            ->where(['ApProfileExits.ap_profile_id' => $ap_profile_id])
            ->all();

        foreach($q_r as $m){
            $exit_entries = [];

            foreach($m->ap_profile_exit_ap_profile_entries as $m_e_ent){
                if($m_e_ent->ap_profile_entry_id != 0){
                    array_push($exit_entries, ['name' => $m_e_ent->ap_profile_entry->name]);
                }
                if($m_e_ent->ap_profile_entry_id == 0){
                    array_push($exit_entries, ['name' => 'LAN (If Hardware Supports It)']);
                }  
            }

            array_push($items, [
                'id'            => $m->id,
                'ap_profile_id' => $m->ap_profile_id,
                'type'          => $m->type,
                'vlan'          => intval($m->vlan),
                'connects_with' => $exit_entries

            ]);
        }
        //___ FINAL PART ___
        $this->set([
            'items' => $items,
            'success' => true,
            '_serialize' => ['items','success']
        ]);
    }

    public function apProfileExitAdd(){
        $this->loadModel('ApProfileExitApProfileEntries');
        $this->loadModel('ApProfileExits');

        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }

        $cdata = $this->request->getData();

       // print_r($this->request->data);
       // exit;

        $exitEntity = $this->ApProfileExits->newEntity($cdata);

        if($this->request->getData('type') == 'captive_portal'){
            if(null !== $this->request->getData('auto_dynamic_client')){
                $cdata['auto_dynamic_client'] = 1;
                
                //Get a list of realms if the person selected a list - If it is empty that's fine
                $count      = 0;
                $cdata['realm_list'] = ""; //Prime it
                if (array_key_exists('realm_ids', $cdata)) {
                    foreach($this->request->getData('realm_ids') as $r){
                        if($count == 0){
                            $cdata['realm_list'] = $cdata['realm_ids'][$count];
                        }else{
                            $cdata['realm_list'] = $cdata['realm_list'].",".$cdata['realm_ids'][$count];
                        }  
                        $count++;
                    }
                }
                
            }else{
                $cdata['auto_dynamic_client'] = 0;
            }
            
            if(null !== $this->request->getData('auto_login_page')){
                $cdata['auto_login_page'] = 1;
            }else{
                $cdata['auto_login_page'] = 0;
            }
        }
        
        if ($this->ApProfileExits->save($exitEntity)) {
            $new_id         = $exitEntity->id;
            $ap_profile_id  = $this->request->getData('ap_profile_id');
            
            //---- openvpn_bridge -----
            if($this->request->getData('type') == 'openvpn_bridge'){

                $this->loadModel('OpenvpnServers');
                $this->loadModel('Aps');

                $server_id  = $this->request->getData('openvpn_server_id');
                $q_s        = $this->OpenvpnServers->find()->where(['OpenvpnServers.id' => $server_id])->first();
                $next_ip    = $q_s->vpn_bridge_start_address;

                $q_aps      = $this->Aps->find()->where(['Aps.ap_profile_id' => $ap_profile_id])->all();
 
                //We need to add a VPN entry for all the existing ones so we do not need to add them again
                foreach($q_aps as $ap){
                    $ap_id = $ap->id;
                    $not_available      = true;
                    while($not_available){
                        if($this->_check_if_available($server_id,$next_ip)){
                            $d_vpn_c['ip_address'] = $next_ip;
                            $not_available = false;
                            break;
                        }else{
                            $next_ip = $this->_get_next_ip($next_ip);
                        }
                    }
                    $d_new                      = array();
                    $d_new['mesh_ap_profile']   = 'ap_profile';
                    $d_new['openvpn_server_id'] = $server_id;
                    $d_new['ip_address']        = $next_ip;
                    $d_new['ap_profile_id']     = $ap_profile_id;
                    $d_new['ap_profile_exit_id']= $new_id;
                    $d_new['ap_id']             = $ap->id;
                    
                    $ovpnClientEntity = $this->OpenvpnServerClients->newEntity($d_new);
                    $this->OpenvpnServerClients->save($ovpnClientEntity);
                }
            }
            //---- END openvpn_bridge ------ 

            //===== Captive Portal ==========
            if($this->request->getData('type') == 'captive_portal'){
                $this->loadModel('ApProfileExitCaptivePortals');
                
                $this->request->data['ap_profile_exit_id'] = $new_id;
				$check_items = [
					'swap_octets',
					'mac_auth',
                    'proxy_enable',
                    'dns_manual',
                    'uamanydns',
                    'dnsparanoia',
                    'dnsdesk'
				];
			    foreach($check_items as $i){
			        if(isset($this->request->data[$i])){
			            $this->request->data[$i] = 1;
			        }else{
			            $this->request->data[$i] = 0;
			        }
			    }
			    
			    if($this->request->data['dns_manual'] == 0){
			        //Clear any values
			        $this->request->data['dns1'] = '';
			        $this->request->data['dns2'] = '';
			    }
			    
			    $ent_cp = $this->ApProfileExitCaptivePortals->newEntity($this->request->data);
			    
			    if(!($this->{'ApProfileExitCaptivePortals'}->save($ent_cp))){
                    $this->{'ApProfileExits'}->delete($entity);
                    $message = __('Could not update item');
                    $this->JsonErrors->entityErros($ent_cp,$message); 
                    return;
                }
            }
            //==== End of Captive Portal ====
            
            //===== NAT ==========
            //== Remove any existing ApProfileExitSettings ====
            if($this->request->getData('type') == 'nat'){
                $this->loadModel('ApProfileExitSettings');
                $nat_data               = $this->request->getData();
                $ap_profile_exit_id     = $new_id;
            
                if($nat_data['nat_config'] == 'manual'){
                    foreach(array_keys($nat_data) as $key){
                        if(preg_match('/^nat_/',$key)){
                            if($key == 'nat_config'){
                                continue;
                            }
                            if($nat_data[$key] == ''){
                                continue;
                            }
                            $s_data = ['ap_profile_exit_id' => $ap_profile_exit_id,'name'  => $key,'value' => $nat_data[$key]];
                            $s = $this->{'ApProfileExitSettings'}->newEntity($s_data); 
                            $s = $this->{'ApProfileExitSettings'}->save($s);
                        }
                    }   
                }           
            }       
            //==== END NAT ========

            //Add the entry points
            $count      = 0;
            $entry_ids  = [];
                
            if (array_key_exists('entry_points', $this->request->data)) {
                if(!empty($this->request->data['entry_points'])){
                    foreach($this->request->data['entry_points'] as $e){
                        if($e != ''){
                            array_push($entry_ids,$this->request->data['entry_points'][$count]);
                        }
                        $count++;      
                    }
                }
            }
            
            foreach($entry_ids as $id){	
                $data = [];
                $data['ap_profile_exit_id']  = $new_id;
                $data['ap_profile_entry_id'] = $id;

                $entryPointEntity = $this->ApProfileExitApProfileEntries->newEntity($data);
                $this->ApProfileExitApProfileEntries->save($entryPointEntity);
            }

            $this->set([
                'success' => true,
                '_serialize' => ['success']
            ]);
        }else{
            $message = 'Error';
            $this->set([
                'errors'    => $this->JsonErrors->entityErros($exitEntity, $message),
                'success'   => false,
                'message'   => ['message' => __('Could not create item')],
                '_serialize' => ['errors','success','message']
            ]);
        }
    }

    public function apProfileExitEdit(){

		$user = $this->_ap_right_check();
        if(!$user){
            return;
        }

        if ($this->request->is('post')) {
        
            $this->loadModel('ApProfileExitApProfileEntries');
            $this->loadModel('ApProfileExits');
            $this->loadModel('OpenvpnServers');
            
            //---- openvpn_bridge -----
            if($this->request->getData('type') == 'openvpn_bridge'){
                
                //We will only do the following if the selected OpenvpnServer changed
                $q_exit             = $this->ApProfileExits->find()->where(['ApProfileExits' => $this->request->getData('id')]);
                $current_server_id  = $q_exit->openvpn_server_id;
                $server_id          = $this->request->getData('openvpn_server_id');
                $ap_profile_exit_id = $this->request->getData('id');
                
                if($current_server_id !== $server_id){
                    //Update current list 
                    $current_aps = $this->OpenvpnServerClients->find()->where([
                        'OpenvpnServerClients.openvpn_server_id'     => $current_server_id,
                        'OpenvpnServerClients.mesh_ap_profile'       => 'ap_profile',
                        'OpenvpnServerClients.ap_profile_exit_id'    => $ap_profile_exit_id,

                    ])->all();

                    $q_r        = $this->OpenvpnServers->find()->where(['id' => $server_id])->first();
                        
                    foreach($current_aps as $ap){
                        $next_ip            = $q_r->vpn_bridge_start_address;

                        $not_available      = true;
                        while($not_available){
                            if($this->_check_if_available($server_id,$next_ip)){
                                $d_vpn_c['ip_address'] = $next_ip;
                                $not_available = false;
                                break;
                            }else{
                                $next_ip = $this->_get_next_ip($next_ip);
                            }
                        }      
                        //Update the record
                        $d_apdate                       = [];
                        $d_update['id']                 = $ap->id;
                        $d_update['openvpn_server_id']  = $server_id;
                        $d_update['ip_address']         = $next_ip;

                        $dUpdateEntity = $this->OpenvpnServerClients->newEntity($d_apdate);
                        $this->OpenvpnServerClients->save($dUpdateEntity);
                    }          
                }            
            }
            //---- END openvpn_bridge ------
            

            //===== Captive Portal ==========
            //== First see if we can save the captive portal data ====
            if($this->request->getData('type') == 'captive_portal'){
            
                $this->loadModel('ApProfileExitCaptivePortals');
                $cp_data                = $this->request->data;
                $ap_profile_exit_id     = $this->request->getData('id');
                $ent_cp                 = $this->ApProfileExitCaptivePortals->find()
                    ->where(['ApProfileExitCaptivePortals.ap_profile_exit_id' => $ap_profile_exit_id])
                    ->first();

                if($ent_cp){
                    $cp_id          = $ent_cp->id;
                    $cp_data['id']  = $cp_id;

					$check_items = [
						'swap_octets',
						'mac_auth',
                        'proxy_enable',
                        'dns_manual',
                        'uamanydns',
                        'dnsparanoia',
                        'dnsdesk'
					];
					foreach($check_items as $i){
					    if(isset($this->request->data[$i])){
					        $cp_data[$i] = 1;
					    }else{
					        $cp_data[$i] = 0;
					    }
					}
					
					if( $cp_data['dns_manual'] == 0){
			            //Clear any values
			            $cp_data['dns1'] = '';
			            $cp_data['dns2'] = '';
			        }

                   // print_r($cp_data);
                   $this->{'ApProfileExitCaptivePortals'}->patchEntity($ent_cp, $cp_data);
                   
                   if(!($this->{'ApProfileExitCaptivePortals'}->save($ent_cp))){
                        $message = __('Could not update item');
                        $this->JsonErrors->entityErros($ent_cp,$message); 
                        return;
                    }
                }
            }
            //==== End of Captive Portal ====
            
            //===== NAT ==========
            //== Remove any existing ApProfileExitSettings ====
            if($this->request->getData('type') == 'nat'){
                $this->loadModel('ApProfileExitSettings');
                $nat_data               = $this->request->getData();
                $ap_profile_exit_id     = $nat_data['id'];
            
                $settings = $this->{'ApProfileExitSettings'}->find()
                    ->where(['ApProfileExitSettings.ap_profile_exit_id' => $ap_profile_exit_id])
                    ->all();
                foreach($settings as $s){
                    if(preg_match('/^nat_/',$s->name)){
                        $this->{'ApProfileExitSettings'}->delete($s);
                    }
                }
            
                if($nat_data['nat_config'] == 'manual'){
                    foreach(array_keys($nat_data) as $key){
                        if(preg_match('/^nat_/',$key)){
                            if($key == 'nat_config'){
                                continue;
                            }
                            if($nat_data[$key] == ''){
                                continue;
                            }
                            $s_data = ['ap_profile_exit_id' => $ap_profile_exit_id,'name'  => $key,'value' => $nat_data[$key]];
                            $s = $this->{'ApProfileExitSettings'}->newEntity($s_data); 
                            $s = $this->{'ApProfileExitSettings'}->save($s);
                        }
                    }   
                }           
            }       
            //==== END NAT ========
            
            $this->request->data['realm_list'] = ""; //Prime it
            
            if($this->request->getData('type') == 'captive_portal'){
                if(null !== $this->request->getData('auto_dynamic_client')){
                    $this->request->data['auto_dynamic_client'] = 1;
                    
                    //Get a list of realms if the person selected a list - If it is empty that's fine
                    $count      = 0;
                    if (array_key_exists('realm_ids', $this->request->data)) {
                        foreach($this->request->getData('realm_ids') as $r){
                            if($count == 0){
                                $this->request->data['realm_list'] = $this->request->data['realm_ids'][$count];
                            }else{
                                $this->request->data['realm_list'] = $this->request->data['realm_list'].",".$this->request->data['realm_ids'][$count];
                            }  
                            $count++;
                        }
                    }   
                    
                }else{
                    $this->request->data['auto_dynamic_client'] = 0;
                }
                
                if(null !== $this->request->getData('auto_login_page')){
                    $this->request->data['auto_login_page'] = 1;
                }else{
                    $this->request->data['auto_login_page'] = 0;
                }
            }
            
            $ent_exit = $this->{'ApProfileExits'}->get($this->request->data['id']);
            $this->{'ApProfileExits'}->patchEntity($ent_exit, $this->request->data());
            
            // If the form data can be validated and saved..
            if ($this->{'ApProfileExits'}->save($ent_exit)) {

                //Add the entry points
                $count      = 0;
                $entry_ids  = [];
                $new_id     = $this->request->getData('id');

                //Clear previous ones first:
                $this->{'ApProfileExitApProfileEntries'}->deleteAll(['ApProfileExitApProfileEntries.ap_profile_exit_id' => $new_id]);

                if (array_key_exists('entry_points', $this->request->data)) {
                    if(! empty($this->request->getData('entry_points'))){
                        foreach($this->request->getData('entry_points') as $e){
                            if($e != ''){
                                array_push($entry_ids,$this->request->data['entry_points'][$count]);
                            }
                            $count++;      
                        }
                    }
                }

                foreach($entry_ids as $id){
					$data = [];
                    $data['ap_profile_exit_id']  = $new_id;
                    $data['ap_profile_entry_id'] = $id;

                    $entryPointEntity = $this->ApProfileExitApProfileEntries->newEntity($data);
                    $this->ApProfileExitApProfileEntries->save($entryPointEntity);
                }

                $this->set([
                    'success' => true,
                    '_serialize' => ['success']
                ]);
            }
        } 
    }
    
    public function apProfileExitAddDefaults(){
        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }    
        $data = [];
        $this->loadModel('UserSettings');   
        $q_r = $this->{'UserSettings'}->find()->where(['user_id' => -1])->all();
        if($q_r){
            foreach($q_r as $s){
                //ALL Captive Portal Related default settings will be 'cp_<whatever>'
                if(preg_match('/^cp_/',$s->name)){
                    $name           = preg_replace('/^cp_/', '', $s->name);
                    $data[$name]    = $s->value;     
                }
            
            }
        }
        $this->set([
            'data'      => $data,
            'success'   => true,
            '_serialize'=> ['success', 'data']
        ]);
    }
    
    public function apExperimentalCheck(){
        Configure::load('RadiusDesk'); 
        $active = Configure::read('experimental.active'); //Read the defaults
        $this->set([
            'active'     => $active,
            'success'   => true,
            '_serialize'=> ['success', 'active']
        ]);
    }

    public function apProfileExitView(){
        $this->loadModel('ApProfileExits');

        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }

        $id    = $this->request->getQuery('exit_id');

        $q_r   = $this->ApProfileExits->find()->contain([
            'ApProfileExitApProfileEntries',
            'ApProfileExitCaptivePortals',
            'DynamicDetails',
            'ApProfileExitSettings'
        ])->where(['ApProfileExits.id' => $id])->first();

        //Get the realm list
        if($q_r->realm_list != ''){
            $q_r['realm_records'] = [];
            $q_r['realm_ids'] = [];

            $this->loadModel('Realms');

            if($q_r->realm_list){
                $pieces = explode(",", $q_r->realm_list);

                foreach($pieces as $p){
                    if(is_numeric($p)){
                        //Get the name and id of this realm
                        $q_realm = $this->Realms->find()->where(['Realms.id' => $p])->first();
                        if($q_realm){
                            $r_name = $q_realm->name;
                            array_push($q_r['realm_records'], ['id' => $p, 'name' => $r_name]);
                            array_push($q_r['realm_ids'],$p);
                        }
                    }
                }
            }
        }

        //entry_points
        $q_r['entry_points'] = [];

        foreach($q_r->ap_profile_exit_ap_profile_entries as $i){
            array_push($q_r['entry_points'],intval($i->ap_profile_entry_id));
        }

        if($q_r->ap_profile_exit_captive_portal){
            $q_r['radius_1']        = $q_r->ap_profile_exit_captive_portal->radius_1;
            $q_r['radius_2']        = $q_r->ap_profile_exit_captive_portal->radius_2;
            $q_r['radius_secret']   = $q_r->ap_profile_exit_captive_portal->radius_secret;
            $q_r['uam_url']         = $q_r->ap_profile_exit_captive_portal->uam_url;
            $q_r['uam_secret']      = $q_r->ap_profile_exit_captive_portal->uam_secret;
            $q_r['walled_garden']   = $q_r->ap_profile_exit_captive_portal->walled_garden;
            $q_r['swap_octets']     = $q_r->ap_profile_exit_captive_portal->swap_octets;
			$q_r['mac_auth']        = $q_r->ap_profile_exit_captive_portal->mac_auth;

            //Proxy settings
            $q_r['proxy_enable']    = $q_r->ap_profile_exit_captive_portal->proxy_enable;
            $q_r['proxy_ip']        = $q_r->ap_profile_exit_captive_portal->proxy_ip;
            $q_r['proxy_port']      = intval($q_r->ap_profile_exit_captive_portal->proxy_port);
            $q_r['proxy_auth_username']      = $q_r->ap_profile_exit_captive_portal->proxy_auth_username;
            $q_r['proxy_auth_password']      = $q_r->ap_profile_exit_captive_portal->proxy_auth_password;
            $q_r['coova_optional']  = $q_r->ap_profile_exit_captive_portal->coova_optional;
            
            //DNS settings
            $q_r['dns_manual']      = $q_r->ap_profile_exit_captive_portal->dns_manual;
            $q_r['dns1']            = $q_r->ap_profile_exit_captive_portal->dns1;
            $q_r['dns2']            = $q_r->ap_profile_exit_captive_portal->dns2;
            $q_r['uamanydns']       = $q_r->ap_profile_exit_captive_portal->uamanydns;
            $q_r['dnsparanoia']     = $q_r->ap_profile_exit_captive_portal->dnsparanoia;
            $q_r['dnsdesk']         = $q_r->ap_profile_exit_captive_portal->dnsdesk;
            
            //Upstream VLAN id (if applicable)
            if($q_r->ap_profile_exit_captive_portal->ap_profile_exit_upstream_id){
                $q_r['ap_profile_exit_upstream_id'] = $q_r->ap_profile_exit_captive_portal->ap_profile_exit_upstream_id;
            }else{
                $q_r['ap_profile_exit_upstream_id'] = 0;
            }

        }
        
        if($q_r->dynamic_detail){
           $q_r['dynamic_detail'] =  $q_r->dynamic_detail->name;
        }
        $q_r->rb_nat_config = ['nat_config' =>'auto']; 
        foreach($q_r->ap_profile_exit_settings as $s){
            $q_r->rb_nat_config = ['nat_config' =>'manual'];    
            $q_r->{$s->name}    = $s->value;
        }
        
        $data = $q_r;
        
        unset($data->ap_profile_exit_settings);

        $this->set([
            'data'     => $data,
            'success'   => true,
            '_serialize'=> ['success', 'data']
        ]);
    }
    
    public function apProfileExitUpstreamList(){
        $this->loadModel('ApProfileExits');

        $user = $this->Aa->user_for_token($this);
        if(!$user){   //If not a valid user
            return;
        }
        
        $id     = $this->request->getQuery('ap_profile_id');

        $exit_q_r  = $this->ApProfileExits->find()->where([
            'ApProfileExits.ap_profile_id'   => $id,
            'ApProfileExits.type'            => 'tagged_bridge_l3',
        ])->all();

        $items  = [
            ['name'=> 'LAN (Ethernet0)', 'id' => 0 ]
        ];
        
        foreach($exit_q_r as $i){
            //print_r($i);
            array_push($items,['name' => "VLAN ".$i->vlan, 'id' => intval($i->id)]);
        }
        
        $this->set([
            'items'     => $items,
            'success'   => true,
            '_serialize'=> ['success', 'items']
        ]);
    }

    public function apProfileExitDelete(){
        $this->loadModel('ApProfileExits');

       if (! $this->request->is('post')) {
			throw new MethodNotAllowedException();
		}

        //__ Authentication + Authorization __
        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }

        $user_id    = $user['id'];
        $fail_flag  = false;

	    if(isset($this->request->data['id'])){   //Single item delete
            $message = "Single item ".$this->request->data['id'];

            $id     = $this->request->data['id'];
            $q_r    = $this->ApProfileExits->find()->contain(['ApProfiles'])->where(['ApProfileExits.id' => $this->request->data['id']])->first();

            if($q_r){
                if($q_r->type == 'captive_portal'){
                    $ap_profile_name    = $q_r->ap_profile->name;
                    $ap_profile_name    = preg_replace('/\s+/', '_', $ap_profile_name);

                    $this->DynamicClients->deleteAll(['DynamicClients.nasidentifier LIKE' => "$ap_profile_name"."_%_cp_".$id]);

                    $this->DynamicPairs->deleteAll([
                        'DynamicPairs.value LIKE' => "$ap_profile_name"."_%_cp_".$id,
                        'DynamicPairs.name' => 'nasid',
                    ]);
                }              
                $this->ApProfileExits->delete($q_r); // Go delete yourself!
            }     

        }else{                          //Assume multiple item delete
            foreach($this->request->data as $d){
                $id                 = $d['id'];
                $q_r                = $this->ApProfileExits->find()->contain(['ApProfiles'])->where(['ApProfileExits.id' => $d['id']])->first();
                if($q_r){
                    if($q_r->type == 'captive_portal'){
                        $ap_profile_name    = $q_r->ap_profile->name;
                        $ap_profile_name    = preg_replace('/\s+/', '_', $ap_profile_name);
                        $this->DynamicClients->deleteAll(['DynamicClients.nasidentifier LIKE' => "$ap_profile_name"."_%_cp_".$id]);
                        $this->DynamicPairs->deleteAll([
                            'DynamicPairs.value LIKE' => "$ap_profile_name"."_%_cp_".$id,
                            'DynamicPairs.name' => 'nasid',
                        ]);
                    }
                    $this->ApProfileExits->delete($q_r); // Go delete yourself!
                }
            }
        }  
        $this->set([
            'success' => true,
            '_serialize' => ['success']
        ]);
    }

    public function apProfileEntryPoints(){
        $this->loadModel('ApProfileExits');

        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }

        //Get the ap profile id
        $ap_profile_id    = $this->request->getQuery('ap_profile_id');

        $exit_id = false;

        //Check if the exit_id was included
        if(null !== $this->request->getQuery('exit_id')){
            $exit_id = $this->request->getQuery('exit_id');
        }

        $ent_q_r    = $this->ApProfileEntries->find()->contain(['ApProfileExitApProfileEntries'])->where(['ApProfileEntries.ap_profile_id' => $ap_profile_id])->all();
        //print_r($ent_q_r);

        $items = [];

        foreach($ent_q_r as $i){

            //If this entry point is already associated; we will NOT add it
            if(count($i->ap_profile_exit_ap_profile_entries) == 0){
                $id = intval($i->id);
                $n  = $i->name;
                array_push($items, ['id' => $id, 'name' => $n]);
            }

            //if $exit_id is set; we add it 
            if($exit_id){
                if(count($i->ap_profile_exit_ap_profile_entries) > 0){
                    if($i->ap_profile_exit_ap_profile_entries[0]->ap_profile_exit_id == $exit_id){
                        $id = intval($i->id);
                        $n  = $i->name;
                        array_push($items, ['id' => $id, 'name' => $n]);
                    }
                }
            }
        }
        
        //Eth1 check
        $this->loadModel('ApProfileExitApProfileEntries');

        $q_eth1_entry = $this->ApProfileExitApProfileEntries->find()->contain(['ApProfileExits'])->where([
            'ApProfileExits.ap_profile_id' => $ap_profile_id,
            'ApProfileExitApProfileEntries.ap_profile_entry_id' => 0
        ])->first();
        
        if($q_eth1_entry){
            //Only if it is for this exit
            if($q_eth1_entry->ap_profile_exit_id == $exit_id){
                array_push($items, ['id' => 0, 'name' => "LAN (If Hardware Suports It)"]);
            }   
        }else{
            array_push($items, ['id' => 0, 'name' => "LAN (If Hardware Suports It)"]); //Allow the user not to assign at this stage
        }
         
        $this->set([
            'items' => $items,
            'success' => true,
            '_serialize' => ['items','success']
        ]);
    }
    
    public function  apProfileSettingsView(){   
        $user = $this->Aa->user_for_token($this);
        if(!$user){   //If not a valid user
            return;
        }
        
        $user_id  = $user['id'];
        $tree     = false;     
        $entity = $this->Users->get($user_id); 
        if($this->Users->childCount($entity) > 0){
            $tree = true;
        }
        $data = [];

        $id = $this->request->query['ap_profile_id'];         
        $q  = $this->{'ApProfiles'}->find()->where(['ApProfiles.id' => $id ])->contain(['Users'=> ['fields' => ['Users.username']]])->first(); 
        
        if($q){
            $data['name']                   = $q->name;
            $data['user_id']                = $q->user_id;
            $data['show_owner']             = $tree;        
            if($q->user !== null){
                $data['username']  = "<div class=\"fieldBlue\"> <b>".$q->user->username."</b></div>";
            }else{
                $data['username']  = "<div class=\"fieldRed\"><i class='fa fa-exclamation'></i> <b>(ORPHANED)</b></div>";
            }
            $data['available_to_siblings']  = $q->available_to_siblings;
            $data['enable_alerts']          = $q->enable_alerts;
            $data['enable_overviews']       = $q->enable_overviews;        
        }
        
        $this->set(array(
            'data'      => $data,
            'success'   => true,
            '_serialize'=> array('success', 'data')
        ));
    }
    
    public function apProfileSettingsEdit(){
    
        $user = $this->Aa->user_for_token($this);
        if(!$user){   //If not a valid user
            return;
        }
        $user_id    = $user['id'];
         
        if(isset($this->request->data['user_id'])){
            if($this->request->data['user_id'] == '0'){ //This is the holder of the token - override '0'
                $this->request->data['user_id'] = $user_id;
            }
        }   
        $cdata = $this->request->getData();      
        $check_items = [
			'available_to_siblings',
			'enable_overviews',
			'enable_alerts'		
		];
		
		foreach($check_items as $i){
            if(isset($cdata[$i])){
                $cdata[$i] = 1;
            }else{
                $cdata[$i] = 0;
            }
        }
         
        if ($this->request->is('post')) {
            $e = $this->{$this->main_model}->get($cdata['ap_profile_id']);        
            $this->{$this->main_model}->patchEntity($e, $cdata);
            if($this->{$this->main_model}->save($e)){
                $this->set([
                    'success' => true,
                    '_serialize' => ['success']
                ]);           
            }else{
                $message = __('Could not update item');
                $this->JsonErrors->entityErros($e,$message);  
            }
        } 
    }
    
    
    //====== Common AP settings ================
    //-- View common ap settings --
    public function apCommonSettingsView(){
        $this->loadModel('ApProfileSettings');

		$user = $this->_ap_right_check();
        if(!$user){
            return;
        }

        $id     = $this->request->getQuery('ap_profile_id');
        $data   = $this->_getCommonSettings();
        $q_r    = $this->ApProfileSettings->find()->where(['ApProfileSettings.ap_profile_id' => $id])->contain(['Schedules'])->first();

        if($q_r){  
            //print_r($q_r);
            $data = $q_r;
            //We need to find if possible the number for the timezone
            $ent_tz  = $this->{'Timezones'}->find()->where(['Timezones.name' => $q_r->tz_name])->first();
            if($ent_tz){
                $data['timezone']   = $ent_tz->id;
            }
            if($data['schedule_id'] !== null){
                $data['schedule_name'] = $data->schedule->name;
            }    
        }

        $this->set([
            'data'      => $data,
            'success'   => true,
            '_serialize'=> ['success', 'data']
        ]);
    }
    
    private function _getCommonSettings(){
        Configure::load('ApProfiles'); 
        $data       = Configure::read('common_ap_settings'); //Read the defaults
        
        $this->loadModel('UserSettings');
        $want_these = ['password','country','timezone','heartbeat_dead_after'];          
        $q_r   = $this->{'UserSettings'}->find()->where(['user_id' => -1])->all();
        if($q_r){
            foreach($q_r as $s){
                
                if($s->name == 'password'){
                    $data[]         = $s->value;
                    $data['password_hash']  = $this->_make_linux_password($s->value);   
                }
                
                if($s->name == 'country'){
                    $data[$s->name]  = $s->value;
                } 
                if($s->name == 'heartbeat_dead_after'){
                    $data[$s->name]  = $s->value;
                } 
                if($s->name == 'timezone'){
                    $data[$s->name]  = $s->value;
                    $ent_tz = $this->{'Timezones'}->find()->where(['Timezones.id' => $s->value])->first();
                    if($ent_tz){
                        $data['tz_name'] = $ent_tz->name;
                        $data['tz_value']= $ent_tz->value;
                    }
                }
            }
        }
        return $data;
    }
    
    public function apCommonSettingsEdit(){
        $this->loadModel('ApProfileSettings');

		$user = $this->_ap_right_check();
        if(!$user){
            return;
        }

        $cdata = $this->request->getData();

        if ($this->request->is('post')) {

            $check_items = ['gw_use_previous','gw_auto_reboot','enable_schedules'];
            foreach($check_items as $i){
                if(isset($cdata[$i])){
                    $cdata[$i] = 1;
                }else{
                    $cdata[$i] = 0;
                }
            }

            //Try to find the timezone and its value
            $tz_id  = $this->request->getData('timezone');
            $ent_tz = $this->{'Timezones'}->find()->where(['Timezones.id' => $tz_id])->first();
            if($ent_tz){
                    $cdata['tz_name'] = $ent_tz->name;
                    $cdata['tz_value']= $ent_tz->value;
            }
            
            $new_pwd = $this->_make_linux_password($this->request->getData('password'));
		    $cdata['password_hash'] = $new_pwd;
                 
            $ap_profile_id = $this->request->getData('ap_profile_id');

            //See if there is not already a setting entry
            $ent_setting    = $this->ApProfileSettings->find()->where(['ApProfileSettings.ap_profile_id' => $ap_profile_id])->first();
            
            if($ent_setting){
			    $this->{'ApProfileSettings'}->patchEntity($ent_setting,$cdata);
            }else{ //There was no settings entry so we need to create a new one
                $ent_setting = $this->{'ApProfileSettings'}->newEntity($cdata);
            }    
            
            if ( $this->ApProfileSettings->save($ent_setting)) {
                   $this->set([
                    'success' => true,
                    '_serialize' => ['success']
                ]);
            }
        }
    }
    
    //=== APs Special ====
     public function advancedSettingsForModel(){
        $data   = [];
        $model  = $this->request->getQuery('model');
        $cdata  = $this->request->getQuery();
        $no_overrides = true;

        //Check if there is a ap_id in the request and if so check the current hardware type
        //If the same as the model requested, check if we have overrides
        if(array_key_exists('ap_id', $cdata)){
            $ap_id = $this->request->getQuery('ap_id');
            $this->loadModel('Aps');    
            $q_r  = $this->Aps->find()
                ->contain(['ApWifiSettings'])
                ->where(['Aps.id' => $ap_id,'Aps.hardware' => $model])->first(); //It has to match the id and the model else the person actually selected another model
            if($q_r){
                $q_e = $this->{'Hardwares'}->find()->where(['Hardwares.fw_id' => $model])->contain(['HardwareRadios'])->first();
                $data['radio_count'] = $q_e->radio_count;
                if(count($q_r->ap_wifi_settings) > 0){
                    $no_overrides = false;
                    foreach($q_r->ap_wifi_settings as $s){ 
                        $s_name     = $s->name;
                        $s_value    = $s->value;
                        
                        if(preg_match('/^radio\d+_ht_capab/',$s_name)){
                            if($s_value !== ''){
                                if(isset($data["$s_name"])){
                                    $data["$s_name"] = $data["$s_name"]."\n".$s_value;
                                }else{
                                    $data["$s_name"] = $s_value;
                                }
                            }
                        }else{
                             
                            //Small fix to make boolean from string....
                            if($s_value == 'false'){
                                $s_value = false;
                            }
                            if($s_value == 'true'){
                                $s_value = true;
                            }
                            if($s_value == 'on'){
                                $s_value = true;
                            }
                            $data["$s_name"] = $s_value;
                        }
                    }
                }
            }
        }
             
        if($no_overrides){
            $radio_fields = [
                'disabled','band','mode','width','txpower'
            ];    
            $data   = [];
            
            $data['radio_count'] =0;
            
            if(isset($this->request->query['model'])){
                $model = $this->request->query['model'];
                $q_e = $this->{'Hardwares'}->find()->where(['Hardwares.fw_id' => $model])->contain(['HardwareRadios'])->first();
                if($q_e){
                    $data['radio_count'] = $q_e->radio_count;
                    foreach($q_e->hardware_radios as $hr){    
                        $radio_number   = $hr->radio_number;
                        foreach($radio_fields as $fr){
                            $data['radio'.$radio_number.'_'.$fr] = $hr[$fr];
                        }  
                    }
                } 
            }
        }
       
        $this->set([
            'data' => $data,
            'success' => true,
            '_serialize' => ['data','success']
        ]);
    }

    
    //=== APs CRUD ===
    public function apProfileApIndex(){

        $geo_data   = Configure::read('paths.geo_data');
        $reader     = new Reader($geo_data);

        $this->loadModel('Aps');
        $this->loadModel('ApProfileSettings');

        $user = $this->_ap_right_check();

        if(!$user){
            return;
        }

        $items          = [];
        $total          = 0;
        $ap_profile_id  = $this->request->getQuery('ap_profile_id');
        $q_r            = $this->Aps->find()->where(['Aps.ap_profile_id' => $ap_profile_id])->all();

        //Create a hardware lookup for proper names of hardware
        $hardware = [];
	    $q_hw = $this->{'Hardwares'}->find()->where(['Hardwares.for_ap' => true])->all();
        if($q_hw){
            foreach($q_hw as $hw){
                $id     = $hw->fw_id;
                $name   = $hw->name; 
                $hardware["$id"]= $name;
            }
        }

        foreach($q_r as $m){

            $m->last_contact_human = '';

            if($m->last_contact != null){
                $m->last_contact_human  = $this->TimeCalculations->time_elapsed_string($m->last_contact);
            }

            //----              
            //Some defaults:
            $country_code   = '';
            $country_name  = '';
            $city           = '';
            $postal_code    = '';
            $state_name     = '';
            $state_code     = '';
            
            if($m->last_contact_from_ip != null){

                try {
                    $location = $reader->city($m->last_contact_from_ip);
                } catch (\Exception $e) {
                    //Do Nothing
                }

                if(! empty($location)){
                    if($location->country->isoCode != ''){
                        $country_code = $location->country->isoCode;
                    }
                    if($location->country->name != ''){
                        $country_name = $location->country->name;
                    }
                    if($location->city->name != ''){
                        $city = $location->city->name;
                    }
                    if($location->postal->code != ''){
                        $postal_code = $location->postal->code;
                    }
                    if($location->mostSpecificSubdivision->name != ''){
                        $state_name = $location->mostSpecificSubdivision->name;
                    }
                    if($location->mostSpecificSubdivision->isoCode != ''){
                        $state_code = $location->mostSpecificSubdivision->isoCode;
                    }
                }
            }   
            //----    

            array_push($items, [
                'id'                    => $m->id,
                'ap_profile_id'         => $m->ap_profile_id,
                'name'                  => $m->name,
                'description'           => $m->description,
                'mac'                   => $m->mac,
                'hardware'	            => $m->hardware,
                'hw_human'              => $hardware["$m->hardware"],
                'last_contact_from_ip'	=> $m->last_contact_from_ip,
                'on_public_maps'	    => $m->on_public_maps,
				'last_contact'	        => $m->last_contact,
				"last_contact_human"    => $m->last_contact_human,
				'lat'			        => $m->lat,
				'lng'			        => $m->lon,
				'photo_file_name'	    => $m->photo_file_name,
				'country_code'          => $country_code,
                'country_name'          => $country_name,
                'city'                  => $city,
                'postal_code'           => $postal_code,
            ]);
        }
        //___ FINAL PART ___
        $this->set([
            'items' => $items,
            'success' => true,
            '_serialize' => ['items','success']
        ]);
    }

    public function apProfileApAdd(){

        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }

        $cdata = $this->request->getData();
        //Unset the id
        unset($cdata['id']); //Remove the ID which is set to 0 (Zero) for ADD actions
        
        //TreeTag we now use network_id
        if(isset($cdata['network_id'])){
            $network_id     = $cdata['network_id']; //Will be in format Network_<id>
            $tree_tag_id    =  preg_replace('/^(\w+)_/', '', $network_id);//Then we use that value to populate the tree tag
            $cdata['tree_tag_id'] = $tree_tag_id;
        }
        
        $check_items = [
		    'enable_overviews',
		    'enable_alerts'		
	    ];
	    
	    foreach($check_items as $i){
            if(isset($cdata[$i])){
                $cdata[$i] = 1;
            }else{
                $cdata[$i] = 0;
            }
        }
        
        
        $this->loadModel('Aps');
        $this->loadModel('ApProfileSettings');
        $this->loadModel('ApProfileExits');
        $this->loadModel('OpenvpnServerClients');
        $this->loadModel('ApWifiSettings');
        $this->loadModel('ApConnectionSettings');


        //Get the ApProfile so we can get the user_id nd available_to_siblings for the said ap_profile
        $ap_profile_id  = $this->request->getData('ap_profile_id');

        $ap_profile     = $this->{$this->main_model}->find()->where(['ApProfiles.id' => $ap_profile_id])->first();
        $user_id        = $ap_profile->user_id;
        $a_to_s         = $ap_profile->available_to_siblings;
        $ap_profile_name= $ap_profile->name;
        $ap_profile_name= preg_replace('/\s+/', '_', $ap_profile_name);
        
        $apEntity = $this->Aps->newEntity($cdata);

        if ($this->Aps->save($apEntity)) {

            $new_id = $apEntity->id;
            
            //Delete regardless - it might be there it might be not
	        $this->loadModel('UnknownNodes');
		    $mac = $this->request->getData('mac');
            $this->UnknownNodes->deleteAll(['UnknownNodes.mac' => $mac]);

			
			//__ OpenVPN Bridges _____
			$q_e_vpnb = $this->ApProfileExits->find()->contain(['OpenvpnServers'])->where([
                'ApProfileExits.ap_profile_id'   => $ap_profile_id,
                'ApProfileExits.type'            => 'openvpn_bridge',
            ])->all();
		    
		    foreach($q_e_vpnb as $e){
		        //Get the OpenvpnServer's detail of reach
		        $vpn_server_id  = $e->openvpn_server->id;
		        $exit_id        = $e->id;
		        
		        $d_vpn_c                        = [];
                $d_vpn_c['mesh_ap_profile']     = 'ap_profile';
                $d_vpn_c['openvpn_server_id']   = $vpn_server_id;
                $d_vpn_c['ap_profile_id']       = $ap_profile_id;
                $d_vpn_c['ap_profile_exit_id']  = $exit_id;
                $d_vpn_c['ap_id']               = $new_id;
            
                $next_ip        = $e->openvpn_server->vpn_bridge_start_address;
                $not_available  = true;
                
                while($not_available){
                    if($this->_check_if_available($vpn_server_id,$next_ip)){
                        $d_vpn_c['ip_address'] = $next_ip;
                        $not_available = false;
                        break;
                    }else{
                        $next_ip = $this->_get_next_ip($next_ip);
                    }
                }

                $ovpnServerClientEntity = $this->OpenvpnServerClients->newEntity($d_vpn_c);
                $this->OpenvpnServerClients->save($ovpnServerClientEntity);
		    }	
			//__ END OpenVPN Bridges ___
			
			
			//______________________________________________________________________			
	        //We need to see if there are captive portals defined on the ap_profile
	        //_______________________________________________________________________

	        $q_exits = $this->{$this->main_model}->ApProfileExits->find()->contain(['ApProfileExitCaptivePortals'])->where([
                'ApProfileExits.ap_profile_id'   => $ap_profile_id,
                'ApProfileExits.type'            => 'captive_portal'
            ])->all();

	        foreach($q_exits as $qe){
	            	        
	            $exit_id = $qe->id;
	            
	            $name_no_spaces = $this->request->getData('name');
	            $name_no_spaces = preg_replace('/\s+/', '_', $name_no_spaces);          
	            $dc_data                            = [];
	            $dc_data['user_id']                 = $user_id;
	            $dc_data['available_to_siblings']   = $a_to_s;
	            $dc_data['nasidentifier']           = $ap_profile_name.'_'.$name_no_spaces.'_cp_'.$exit_id;
	            $dc_data['realm_list']              = $qe->realm_list;
	            
	            if($qe->auto_dynamic_client == 1){  //It has to be enabled
	                $this->_add_dynamic($dc_data);
	            }
	            
	            if($qe->auto_login_page == 1){  //It has to be enabled
	                $dc_data['dynamic_detail_id'] = $qe->dynamic_detail_id;
	                $this->_add_dynamic_pair($dc_data);
	            }
	        }
	        //_______________________________________________________________________
	        
	        if($this->request->getData('internet_connection') == 'wifi'){
                foreach(array_keys($this->request->data) as $key){
                    if(preg_match('/^wbw_/',$key)){
                        //If it comes from the auto cp it will have wbw_freq whech we have to determine based on the hardware if it will be radio0 or radio1
                        //If it comes from the GUI it will have wbw_device
                        if($key == 'wbw_freq'){
                            $freq       = $this->request->data["$key"];
                            $key        = 'wbw_device'; //We replace wbw_freq with wbw_device
                            $hardware   = $this->request->data['hardware'];
                            $device     = 'radio'.$this->_get_radio_for($hardware,$freq); 
                            $this->request->data["$key"] = $device;
                        }
                    
                        $d_wbw = [];
                        $d_wbw['ap_id']      = $new_id;
                        $d_wbw['grouping']   = 'wbw_setting';
                        $d_wbw['name']       = preg_replace('/^wbw_/', '', $key);
                        $d_wbw['value']      = $this->request->data["$key"];
                                        
                        $ent_wbw = $this->{'ApConnectionSettings'}->newEntity($d_wbw);  
                        $this->{'ApConnectionSettings'}->save($ent_wbw);    
                    }
                }  
            }
            
            if($this->request->getData('internet_connection') == 'wan_static'){
                foreach(array_keys($this->request->data) as $key){
                    if(preg_match('/^wan_static_/',$key)){
                        $d_ws = [];
                        $d_ws['ap_id']      = $new_id;
                        $d_ws['grouping']   = 'wan_static_setting';
                        $d_ws['name']       = preg_replace('/^wan_static_/', '', $key);
                        $d_ws['value']      = $this->request->data["$key"];
                        $ent_ws = $this->{'ApConnectionSettings'}->newEntity($d_ws);  
                        $this->{'ApConnectionSettings'}->save($ent_ws);    
                    }
                }               
            }
            
            if($this->request->getData('internet_connection') == 'wan_pppoe'){
                foreach(array_keys($this->request->data) as $key){
                    if(preg_match('/^wan_pppoe_/',$key)){
                        $d_ws = [];
                        $d_ws['ap_id']      = $new_id;
                        $d_ws['grouping']   = 'wan_pppoe_setting';
                        $d_ws['name']       = preg_replace('/^wan_pppoe_/', '', $key);
                        $d_ws['value']      = $this->request->data["$key"];
                        $ent_ws = $this->{'ApConnectionSettings'}->newEntity($d_ws);  
                        $this->{'ApConnectionSettings'}->save($ent_ws);    
                    }
                }               
            } 
            
            if($this->request->getData('internet_connection') == 'wifi_static'){
                foreach(array_keys($this->request->data) as $key){
                    if(preg_match('/^wifi_static_/',$key)){
                        $d_ws = [];
                        $d_ws['ap_id']      = $new_id;
                        $d_ws['grouping']   = 'wifi_static_setting';
                        $d_ws['name']       = preg_replace('/^wifi_static_/', '', $key);
                        $d_ws['value']      = $this->request->data["$key"];
                        $ent_ws = $this->{'ApConnectionSettings'}->newEntity($d_ws);  
                        $this->{'ApConnectionSettings'}->save($ent_ws);    
                    }
                }               
            }
            
            if($this->request->getData('internet_connection') == 'wifi_pppoe'){
                foreach(array_keys($this->request->data) as $key){
                    if(preg_match('/^wifi_pppoe_/',$key)){
                        $d_ws = [];
                        $d_ws['ap_id']      = $new_id;
                        $d_ws['grouping']   = 'wifi_pppoe_setting';
                        $d_ws['name']       = preg_replace('/^wifi_pppoe_/', '', $key);
                        $d_ws['value']      = $this->request->data["$key"];
                        $ent_ws = $this->{'ApConnectionSettings'}->newEntity($d_ws);  
                        $this->{'ApConnectionSettings'}->save($ent_ws);    
                    }
                }               
            } 
            
            if($this->request->getData('internet_connection') == 'qmi'){
                foreach(array_keys($this->request->data) as $key){
                    if(preg_match('/^qmi_/',$key)){
                        $d_ws = [];
                        $d_ws['ap_id']      = $new_id;
                        $d_ws['grouping']   = 'qmi_setting';
                        $d_ws['name']       = preg_replace('/^qmi_/', '', $key);
                        $d_ws['value']      = $this->request->data["$key"];
                        $ent_ws = $this->{'ApConnectionSettings'}->newEntity($d_ws);  
                        $this->{'ApConnectionSettings'}->save($ent_ws);    
                    }
                }               
            }        
            
            if(isset($this->request->data['chk_no_controller'])){
                $d_wbw['ap_id']    = $new_id;
                $d_wbw['grouping']   = 'reboot_setting';
                $d_wbw['name']       = 'controller_reboot_time';
                $d_wbw['value']      = $this->request->data['controller_reboot_time'];
                $ent_wbw = $this->{'ApConnectionSettings'}->newEntity($d_wbw);  
                $this->{'ApConnectionSettings'}->save($ent_wbw);
            }
            
            if(isset($this->request->data['chk_daily_reboot'])){
                $d_wbw['ap_id']    = $new_id;
                $d_wbw['grouping']   = 'reboot_setting';
                $d_wbw['name']       = 'reboot_at';
                $d_wbw['value']      = $this->request->data['reboot_at'];
                $ent_wbw = $this->{'ApConnectionSettings'}->newEntity($d_wbw);  
                $this->{'ApConnectionSettings'}->save($ent_wbw);
            }     

            //---------Add WiFi settings for this ap ------
            //--Clean up--
            $a_id = $new_id;
            foreach(array_keys($this->request->data) as $key){                
                if(preg_match('/^radio\d+_(disabled|band|mode|width|txpower|include_distance|distance|include_beacon_int|beacon_int|ht_capab|mesh|ap|config|channel_five|channel_two|noscan)/',$key)){           
                    if(preg_match('/^radio\d+_ht_capab/',$key)){
                        $pieces = explode("\n", $this->request->data["$key"]);
                        foreach($pieces as $p){

                            $d_setting = [];
                            $d_setting['ap_id']     = $a_id;
                            $d_setting['name']      = $key;
                            $d_setting['value']     = $p;                      
                            $ent_s = $this->{'ApWifiSettings'}->newEntity($d_setting); 
                            if(!$this->{'ApWifiSettings'}->save($ent_s)){
                                $message = __('Could not add item');
                                $this->JsonErrors->entityErros($ent_s,$message);
                                return;
                            }
                        }
                    }else{
                        $d_setting = [];
                        $d_setting['ap_id']     = $a_id;
                        $d_setting['name']      = $key;
                        $d_setting['value']     = $this->request->data["$key"];
                        $ent_s = $this->{'ApWifiSettings'}->newEntity($d_setting);  
                        if(!$this->{'ApWifiSettings'}->save($ent_s)){
                            $message = __('Could not add item');
                            $this->JsonErrors->entityErros($ent_s,$message);
                            return;
                        }
                    }
                }                                
            }
            //------- END Add settings for this ap ---

            $this->set([
                'success' => true,
                '_serialize' => ['success']
            ]);
        }else{
            $message = __('Could not create item');
            $this->JsonErrors->entityErros($apEntity,$message);
        }
    }
    
    public function apProfileApDelete(){

        $this->loadModel('Aps');
        $this->loadModel('DynamicClients');
        $this->loadModel('DynamicPairs');

        if (! $this->request->is('post')) {
			throw new MethodNotAllowedException();
		}

        //__ Authentication + Authorization __
        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }

        $user_id    = $user['id'];
        $fail_flag  = false;

        $cdata = $this->request->getData();
	    if(isset($cdata['id'])){   //Single item delete
            $message  = "Single item ". $cdata['id'];

            $q_r = $this->Aps->find()->contain(['ApProfiles'])->where(['Aps.id' => $cdata['id']])->first();

            if($q_r){
                $ap_profile_name    = $q_r->ap_profile->name;
                $ap_profile_name    = preg_replace('/\s+/', '_', $ap_profile_name);
                $ap_name            = $q_r->name;
                $ap_name            = preg_replace('/\s+/', '_', $ap_name);

                $this->DynamicClients->deleteAll(['DynamicClients.nasidentifier LIKE' => "$ap_profile_name".'_'.$ap_name."_cp_%"]);
                $this->DynamicPairs->deleteAll(
                            [
                                'DynamicPairs.value LIKE' => "$ap_profile_name".'_'.$ap_name."_cp_%",
                                'DynamicPairs.name' => 'nasid',
                            ]);

                $this->Aps->delete($q_r);
            }
        }else{                          //Assume multiple item delete
            foreach($cdata as $d){
                $id     = $d['id'];
                $q_r    = $this->Aps->find()->contain(['ApProfiles'])->where(['Aps.id' => $d['id']])->first();
                if($q_r){
                    $ap_profile_name    = $q_r->ap_profile->name;
                    $ap_profile_name    = preg_replace('/\s+/', '_', $ap_profile_name);
                    $ap_name            = $q_r->name;
                    $ap_name            = preg_replace('/\s+/', '_', $ap_name);

                    $this->DynamicClients->deleteAll(['DynamicClients.nasidentifier LIKE' => "$ap_profile_name".'_'.$ap_name."_cp_%"]);
                    $this->DynamicPairs->deleteAll(
                        [
                            'DynamicPairs.value LIKE' => "$ap_profile_name".'_'.$ap_name."_cp_%",
                            'DynamicPairs.name' => 'nasid',
                        ]);

                    $this->Aps->delete($q_r);
                }
            }
        }  
        $this->set([
            'success' => true,
            '_serialize' => ['success']
        ]);
    }
    
    
    public function apProfileApEdit(){

		$user = $this->_ap_right_check();
        if(!$user){
            return;
        }
        
        $this->loadModel('Aps');
        $this->loadModel('ApProfileSettings');
        $this->loadModel('ApProfileExits');
        $this->loadModel('OpenvpnServerClients');
        $this->loadModel('ApWifiSettings');
        $this->loadModel('ApConnectionSettings');
        $this->loadModel('DynamicClients');
        $this->loadModel('DynamicPairs');

        $cdata = $this->request->getData();

        if ($this->request->is('post')) {

			$move_ap_profiles	= false;
			
			$check_items = [
			    'enable_overviews',
			    'enable_alerts',
			    'enable_schedules'	
		    ];
		    
		    foreach($check_items as $i){
                if(isset($cdata[$i])){
                    $cdata[$i] = 1;
                }else{
                    $cdata[$i] = 0;
                }
            }
			
			//TreeTag we now use network_id
            if(isset($cdata['network_id'])){
                $network_id     = $cdata['network_id']; //Will be in format Network_<id>
                $tree_tag_id    =  preg_replace('/^(\w+)_/', '', $network_id);//Then we use that value to populate the tree tag
                $cdata['tree_tag_id'] = $tree_tag_id;
            }
			
            //Moving an Access Point from one Profile to another is more involved
            //For this we will do the following
            // 1.) Delete the Ap
            // 2.) Flag it as a move between profiles
            // 3.) Delete related DynamicPair and DynamicClient entries
            
			if (array_key_exists('ap_profile_id', $cdata)) {
				$new_ap_profile_id 	= $this->request->getData('ap_profile_id');

				$q_r = $this->Aps->find()->contain(['ApProfiles'])->where(['Aps.id' => $this->request->getData('id')])->first();

				if($q_r){
                    $current_id = $q_r->ap_profile_id;
                    $current_ap_id = $q_r->id;

                    if($current_id != $new_ap_profile_id){	//Delete it if the ap profile changed
                        $this->Aps->deleteAll(['Aps.id' => $current_ap_id]);

                        $move_ap_profiles   = true;

                        $ap_profile_name    = $q_r->name;
                        $ap_profile_name    = preg_replace('/\s+/', '_', $ap_profile_name);

                        $this->DynamicClients->deleteAll(['DynamicClient.nasidentifier LIKE' => "$ap_profile_name"."_%_cp_".$current_ap_id]);
                        $this->DynamicPairs->deleteAll(
                            [
                                'DynamicPairs.value LIKE' => "$ap_profile_name"."_%_cp_".$current_ap_id,
                                'DynamicPairs.name' => 'nasid',
                            ]);
                    }

                    //See if we have a change in the name of the AP
                    $old_name = $q_r->name;
                    $new_name = $this->request->getData('name');
                    if($old_name != $new_name){
                        $ap_profile_name = $q_r->ap_profile->name;
                        $ap_profile_name = preg_replace('/\s+/', '_', $ap_profile_name);
                        $this->_change_dynamic_shortname($ap_profile_name,$old_name,$new_name); //This is on the NAS Devices
                    }

                    //if(true){
                    $editEntity = $this->Aps->get($current_ap_id);

                    $apEntity = $this->Aps->patchEntity($editEntity, $cdata);

                    if ($this->Aps->save($apEntity)) {
                        $new_id = $apEntity->id;
                                           
                        //Check if we have web-by-wifi enabled
                        $this->{'ApConnectionSettings'}->deleteAll([
                            'ApConnectionSettings.ap_id' => $new_id,
                            'ApConnectionSettings.grouping' => 'wbw_setting'
                        ]);               
                        if($this->request->getData('internet_connection') == 'wifi'){
                            foreach(array_keys($this->request->data) as $key){
                                if(preg_match('/^wbw_/',$key)){
                                    $d_wbw = [];
                                    $d_wbw['ap_id']      = $new_id;
                                    $d_wbw['grouping']   = 'wbw_setting';
                                    $d_wbw['name']       = preg_replace('/^wbw_/', '', $key);
                                    $d_wbw['value']      = $this->request->data["$key"];
                                    $ent_wbw = $this->{'ApConnectionSettings'}->newEntity($d_wbw);  
                                    $this->{'ApConnectionSettings'}->save($ent_wbw);    
                                }
                            }  
                        }else{
                            //Looks like they disabled it so we need to remove any channel overrides
                            $this->{'ApConnectionSettings'}->deleteAll([
                                'ApConnectionSettings.ap_id' => $new_id,
                                'ApConnectionSettings.grouping' => 'wbw_info'
                            ]);  
                        }
                        
                        $this->{'ApConnectionSettings'}->deleteAll([ //
                            'ApConnectionSettings.ap_id' => $new_id,
                            'ApConnectionSettings.grouping' => 'wan_static_setting'
                        ]);    
                        
                        if($this->request->getData('internet_connection') == 'wan_static'){
                            foreach(array_keys($this->request->data) as $key){
                                if(preg_match('/^wan_static_/',$key)){
                                    $d_ws = [];
                                    $d_ws['ap_id']      = $new_id;
                                    $d_ws['grouping']   = 'wan_static_setting';
                                    $d_ws['name']       = preg_replace('/^wan_static_/', '', $key);
                                    $d_ws['value']      = $this->request->data["$key"];
                                    $ent_ws = $this->{'ApConnectionSettings'}->newEntity($d_ws);  
                                    $this->{'ApConnectionSettings'}->save($ent_ws);    
                                }
                            }               
                        } 
                        
                        $this->{'ApConnectionSettings'}->deleteAll([ //
                            'ApConnectionSettings.ap_id' => $new_id,
                            'ApConnectionSettings.grouping' => 'wan_pppoe_setting'
                        ]);    
                        
                        if($this->request->getData('internet_connection') == 'wan_pppoe'){
                            foreach(array_keys($this->request->data) as $key){
                                if(preg_match('/^wan_pppoe_/',$key)){
                                    $d_ws = [];
                                    $d_ws['ap_id']      = $new_id;
                                    $d_ws['grouping']   = 'wan_pppoe_setting';
                                    $d_ws['name']       = preg_replace('/^wan_pppoe_/', '', $key);
                                    $d_ws['value']      = $this->request->data["$key"];
                                    $ent_ws = $this->{'ApConnectionSettings'}->newEntity($d_ws);  
                                    $this->{'ApConnectionSettings'}->save($ent_ws);    
                                }
                            }               
                        }
                        
                        $this->{'ApConnectionSettings'}->deleteAll([ //
                            'ApConnectionSettings.ap_id' => $new_id,
                            'ApConnectionSettings.grouping' => 'wifi_static_setting'
                        ]);    
                        
                        if($this->request->getData('internet_connection') == 'wifi_static'){
                            foreach(array_keys($this->request->data) as $key){
                                if(preg_match('/^wifi_static_/',$key)){
                                    $d_ws = [];
                                    $d_ws['ap_id']      = $new_id;
                                    $d_ws['grouping']   = 'wifi_static_setting';
                                    $d_ws['name']       = preg_replace('/^wifi_static_/', '', $key);
                                    $d_ws['value']      = $this->request->data["$key"];
                                    $ent_ws = $this->{'ApConnectionSettings'}->newEntity($d_ws);  
                                    $this->{'ApConnectionSettings'}->save($ent_ws);    
                                }
                            }               
                        }                           
                        
                        $this->{'ApConnectionSettings'}->deleteAll([ //
                            'ApConnectionSettings.ap_id' => $new_id,
                            'ApConnectionSettings.grouping' => 'wifi_pppoe_setting'
                        ]);    
                        
                        if($this->request->getData('internet_connection') == 'wifi_pppoe'){
                            foreach(array_keys($this->request->data) as $key){
                                if(preg_match('/^wifi_pppoe_/',$key)){
                                    $d_ws = [];
                                    $d_ws['ap_id']      = $new_id;
                                    $d_ws['grouping']   = 'wifi_pppoe_setting';
                                    $d_ws['name']       = preg_replace('/^wifi_pppoe_/', '', $key);
                                    $d_ws['value']      = $this->request->data["$key"];
                                    $ent_ws = $this->{'ApConnectionSettings'}->newEntity($d_ws);  
                                    $this->{'ApConnectionSettings'}->save($ent_ws);    
                                }
                            }               
                        } 
                        
                        $this->{'ApConnectionSettings'}->deleteAll([ //
                            'ApConnectionSettings.ap_id' => $new_id,
                            'ApConnectionSettings.grouping' => 'qmi_setting'
                        ]);    
                        
                        if($this->request->getData('internet_connection') == 'qmi'){
                            foreach(array_keys($this->request->data) as $key){
                                if(preg_match('/^qmi_/',$key)){
                                    $d_ws = [];
                                    $d_ws['ap_id']      = $new_id;
                                    $d_ws['grouping']   = 'qmi_setting';
                                    $d_ws['name']       = preg_replace('/^qmi_/', '', $key);
                                    $d_ws['value']      = $this->request->data["$key"];
                                    $ent_ws = $this->{'ApConnectionSettings'}->newEntity($d_ws);  
                                    $this->{'ApConnectionSettings'}->save($ent_ws);    
                                }
                            }               
                        }                                  
                        
                        //Check if any of the reboot things are specified
                        $this->{'ApConnectionSettings'}->deleteAll([
                            'ApConnectionSettings.ap_id' => $new_id,
                            'ApConnectionSettings.grouping' => 'reboot_setting'
                        ]);       
                        
                        if(isset($this->request->data['chk_no_controller'])){
                            $d_wbw['ap_id']    = $new_id;
                            $d_wbw['grouping']   = 'reboot_setting';
                            $d_wbw['name']       = 'controller_reboot_time';
                            $d_wbw['value']      = $this->request->data['controller_reboot_time'];
                            $ent_wbw = $this->{'ApConnectionSettings'}->newEntity($d_wbw);  
                            $this->{'ApConnectionSettings'}->save($ent_wbw);
                        }
                        
                        if(isset($this->request->data['chk_daily_reboot'])){
                            $d_wbw['ap_id']    = $new_id;
                            $d_wbw['grouping']   = 'reboot_setting';
                            $d_wbw['name']       = 'reboot_at';
                            $d_wbw['value']      = $this->request->data['reboot_at'];
                            $ent_wbw = $this->{'ApConnectionSettings'}->newEntity($d_wbw);  
                            $this->{'ApConnectionSettings'}->save($ent_wbw);
                        }
                        

                        //---------Add WiFi settings for this ap ------
                        //--Clean up--
                        $a_id = $this->request->getData('id');

                        $this->ApWifiSettings->deleteAll(['ApWifiSettings.ap_id' => $a_id]);

                        //Check if the radio0_enable is perhaps missing
                        if(array_key_exists('radio0_enable', $cdata)) {
                            $cdata['radio0_disabled'] = 0;
                        }else{
                            $cdata['radio0_disabled'] = 1;
                        }

                        //Check for radio1 -> First we need to be sure there are a radio1!
                        if(array_key_exists('radio1_band', $cdata)) {
                            if(array_key_exists('radio1_enable', $cdata)) {
                                $cdata['radio1_disabled'] = 0;
                            }else{
                                $cdata['radio1_disabled'] = 1;
                            }
                        }
                        
                        //--Clean up--
                        $this->{'ApWifiSettings'}->deleteAll(['ApWifiSettings.ap_id' => $a_id]);

                        foreach(array_keys($cdata) as $key){
                            if(preg_match('/^radio\d+_(disabled|band|mode|width|txpower|include_distance|distance|include_beacon_int|beacon_int|ht_capab|mesh|ap|config|channel_five|channel_two|noscan)/',$key)){  
                                if(preg_match('/^radio\d+_ht_capab/',$key)){
                                    $pieces = explode("\n", $this->request->data["$key"]);
                                    foreach($pieces as $p){
                                        $d_setting = [];
                                        $d_setting['ap_id']     = $a_id;
                                        $d_setting['name']      = $key;
                                        $d_setting['value']     = $p;
                                        $ent_s = $this->{'ApWifiSettings'}->newEntity($d_setting);  
                                        $this->{'ApWifiSettings'}->save($ent_s);
                                    }
                                }else{
                                    $d_setting = [];
                                    $d_setting['ap_id']     = $a_id;
                                    $d_setting['name']      = $key;
                                    $d_setting['value']     = $this->request->data["$key"];
                                    $ent_s = $this->{'ApWifiSettings'}->newEntity($d_setting);  
                                    $this->{'ApWifiSettings'}->save($ent_s);
                                }
                            }
                        }
                }

                //------- END Add settings for this ap ---

                $this->set([
                    'success' => true,
                    '_serialize' => ['success']
                ]);
			}

            }else{
                $message = 'Error';
                $this->set([
                    'errors'    => $this->JsonErrors->entityErros($apEntity, $message),
                    'success'   => false,
                    'message'   => ['message' => __('Could not create item')],
                    '_serialize' => ['errors','success','message']
                ]);
            }
        } 
    }
    
     public function apProfileApView(){

        $this->loadModel('Aps');
        $this->loadModel('ApWifiSettings');

        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }
        
        $data = [];

        $id   = $this->request->getQuery('ap_id');
        $q_r  = $this->Aps->find()->contain(['ApWifiSettings','ApConnectionSettings','Schedules'])->where(['Aps.id' => $id])->first();
        
        if($q_r){
        
            if($q_r->schedule !== null){
                $data['schedule_name'] = $q_r->schedule->name;
            }       
            $fields     = $this->{'Aps'}->schema()->columns();
            foreach($fields as $field){
                $data["$field"] = $q_r->{"$field"};  
            }
            $hardware = $q_r->hardware;
            $data['internet_connection'] = 'auto_detect';
        
            foreach($q_r->ap_connection_settings as $ncs){
                if($ncs->grouping == 'wbw_setting'){
                    $data['internet_connection'] = 'wifi';
                    $wbw_n           = 'wbw_'.$ncs->name;
                    $data[$wbw_n]    = $ncs->value;
                }
                
                if($ncs->grouping == 'wan_static_setting'){
                    $data['internet_connection'] = 'wan_static';
                    $ws_n           = 'wan_static_'.$ncs->name;
                    $data[$ws_n]    = $ncs->value;
                }
                
                if($ncs->grouping == 'wan_pppoe_setting'){
                    $data['internet_connection'] = 'wan_pppoe';
                    $ws_n           = 'wan_pppoe_'.$ncs->name;
                    $data[$ws_n]    = $ncs->value;
                }
                
                if($ncs->grouping == 'wifi_static_setting'){
                    $data['internet_connection'] = 'wifi_static';
                    $ws_n           = 'wifi_static_'.$ncs->name;
                    $data[$ws_n]    = $ncs->value;
                }
                
                if($ncs->grouping == 'wifi_pppoe_setting'){
                    $data['internet_connection'] = 'wifi_pppoe';
                    $ws_n           = 'wifi_pppoe_'.$ncs->name;
                    $data[$ws_n]    = $ncs->value;
                }
                
                if($ncs->grouping == 'qmi_setting'){
                    $data['internet_connection'] = 'qmi';
                    $ws_n           = 'qmi_'.$ncs->name;
                    $data[$ws_n]    = $ncs->value;
                }
                
                if($ncs->grouping == 'reboot_setting'){
                    if($ncs->name == 'controller_reboot_time'){
                        $data['chk_no_controller'] = 'on';
                        $data['controller_reboot_time'] = $ncs->value;
                    }
                    
                    if($ncs->name == 'reboot_at'){
                        $data['chk_daily_reboot'] = 'on';
                        $data['reboot_at'] = $ncs->value;  
                    }             
                }      
            }
            
            //Grouping
            $data['tree_tag_id']            = $q_r->tree_tag_id;
            $tree_tag                       = $this->_tree_tags($q_r);
            if($tree_tag['value'] == 'not_tagged'){
                $data['tag_path']          = "<div class=\"fieldGrey\"><i class='fa fa-check-circle'></i> <b>(NOT IN GROUP)</b></div>";
            }elseif($tree_tag['value'] == 'orphaned'){
                $data['tag_path']          = "<div class=\"fieldRed\"><i class='fa fa-exclamation'></i> <b>(ORPHANED)</b></div>";
            }else{     
                $data['tag_path']   = "<div class=\"fieldBlue\" style=\"text-align:left;\"> <b>".$tree_tag['value']."</b></div>";
            }
            $data['network_id'] = $tree_tag['network_id'];                    
        }   

        //Return the Advanced WiFi Settings...
        if(count($q_r->ap_wifi_settings) > 0){             
            $radio1_flag    = false;
            $r0_ht_capab    = [];
            $r1_ht_capab    = [];

            foreach($q_r->ap_wifi_settings as $s){
                $s_name     = $s->name;
                $s_value    = $s->value;
                if($s_name == 'radio1_txpower'){
                    $radio1_flag = true;
                }

                if(!(preg_match('/^radio\d+_ht_capab/',$s_name))){
                    $data["$s_name"] = "$s_value";
                }else{
                    if($s_name == 'radio0_ht_capab'){
                        array_push($r0_ht_capab,$s_value);
                    }
                    if($s_name == 'radio1_ht_capab'){
                        array_push($r1_ht_capab,$s_value);
                    }
                }
            }

            $data['radio0_ht_capab'] = implode("\n",$r0_ht_capab);
            if($radio1_flag){
                $data['radio1_ht_capab'] = implode("\n",$r1_ht_capab);
            }
        
        }else{ 
            //Get the defaults from the DB---
            $radio_fields = [
                'disabled','band','mode','width','txpower','include_beacon_int',
                'beacon_int','include_distance','distance','ht_capab','mesh','ap','config'
            ];          
            $q_e = $this->{'Hardwares'}->find()->where(['Hardwares.fw_id' => $hardware])->contain(['HardwareRadios'])->first();         
            if($q_e){
                foreach($q_e->hardware_radios as $hr){    
                    $radio_number   = $hr->radio_number;
                    foreach($radio_fields as $fr){
                    
                        if($fr == 'hwmode'){
                            
                            if($hr[$fr] == '11g'){
                                $data['radio'.$radio_number.'_band'] = '24';
                            }
                            
                            if($hr[$fr] == '11a'){
                                $data['radio'.$radio_number.'_band'] = '5';
                            } 
                            if($hr[$fr] == '11a_ac'){
                                $data['radio'.$radio_number.'_band']   = '5';
                                $data['device_type']                   = 'ac';
                            } 
                              
                        }elseif($fr == 'disabled'){
                            $data['radio'.$radio_number.'_enabled'] = !$hr[$fr];
                        }else{
                            $data['radio'.$radio_number.'_'.$fr] = $hr[$fr];
                        }
                    }  
                }
            } 
        }

		$q_r->ap_profile_id = intval($q_r->ap_profile_id);
		

        $this->set([
            'data'      => $data,
            'success'   => true,
            '_serialize'=> ['success', 'data']
        ]);
    }
   
	
	private function _find_parents($id){

        $q_r        = $this->Users->find('path', ['for' => $id]);
        $path_string= '';
        if($q_r){

            foreach($q_r as $line_num => $i){
                $username       = $i->username;
                if($line_num == 0){
                    $path_string    = $username;
                }else{
                    $path_string    = $path_string.' -> '.$username;
                }
            }
            if($line_num > 0){
                return $username." (".$path_string.")";
            }else{
                return $username;
            }
        }else{
            return __("orphaned");
        }
    }

    private function _is_sibling_of($parent_id,$user_id){
        $q_r        = $this->Users->find(['path' => ['for' => $user_id]]);
        foreach($q_r as $i){
            $id = $i->id;
            if($id == $parent_id){
                return true;
            }
        }
        //No match
        return false;
    }

    
    private function _get_action_flags($owner_id,$user){
        if($user['group_name'] == Configure::read('group.admin')){  //Admin
            return ['update' => true, 'delete' => true, 'view' => true];
        }

        

        if($user['group_name'] == Configure::read('group.ap')){  //AP
            $user_id = $user['id'];

            //test for self
            if($owner_id == $user_id){
                return ['update' => true, 'delete' => true, 'view' => true];
            }
            //Test for Parents
            foreach($this->parents as $i){
                if($i->id == $owner_id){

                    $edit = false;
                    $view = false;

                    //Here we do a special thing to see if the owner of the ap profile perhaps allowed the person beneath him to edit and view the ap_profile
                    if($this->Acl->check(['model' => 'Users', 'foreign_key' => $user_id], $this->base.'apProfileEntryEdit')){
                        $edit = true;
                    }

                    if($this->Acl->check(['model' => 'Users', 'foreign_key' => $user_id], $this->base.'apProfileEntryView')){
                        $view = true;
                    }
                    return ['update' => $edit, 'delete' => false, 'view' => $view ];
                }
            }

            //Test for Children
            foreach($this->children as $i){
                if($i->id == $owner_id){
                    return ['update' => true, 'delete' => true, 'view' => true];
                }
            }  
        }
    }
    
    private function _make_linux_password($pwd){
		return exec("openssl passwd -1 $pwd");
	}
    
    private function _get_dead_after($ap_profile_id){
    
		$this->loadModel('UserSettings');        
        $q_r   = $this->{'UserSettings'}->find()->where(['user_id' => -1,'name' => 'heartbeat_dead_after'])->first();
        if($q_r){
            $dead_after = $q_r->value;
        }
		$ap_s = $this->{$this->main_model}->find()->contain(['ApProfileSettings'])->where(['ApProfileSettings.ap_profile_id' => $ap_profile_id])->first();

		if($ap_s){
            $dead_after = $ap_s->ap_profile_setting->heartbeat_dead_after;
        }
		return $dead_after;
	}
	
	private function _tree_tags($entity){
	    $tag_path = [];    
        $tag_path['value'] = 'not_tagged';
        $tag_path['network_id'] = '';  
        if($entity->tree_tag_id !== null){    
            //Make sure the TreeTag exists
            $networks_check = $this->{'Networks'}->find()->where(['Networks.id' => $entity->tree_tag_id])->first();
            if($networks_check){
                $tag_path['network_id'] = 'network_'.$networks_check->id;
                $site_check = $this->{'Sites'}->find()->where(['Sites.id' => $networks_check->site_id])->first();
                if($site_check){
                    $cloud_check = $this->{'Clouds'}->find()->where(['Clouds.id' => $site_check->cloud_id])->first();
                    if($cloud_check){
                        $tag_path['value'] =  ' <i class="fa fa-cloud"></i> '.
                            $cloud_check->name.'<br>'.
                            ' <i class="fa fa-building"></i> '.
                            $site_check->name.'<br>'.
                            ' <i class="fa fa-sitemap"></i> '.
                            $networks_check->name;
                    }
                    //$tag_path =  ' <i class="fa fa-building"></i> '.$site_check->name.'<br> <i class="fa fa-sitemap"></i> '.$networks_check->name;
                }
                 
               
            }else{
                $tag_path['value'] = "orphaned";
            }
        }  
        return $tag_path;
    }
	
	//----- Menus ------------------------
	
    public function menuForGrid(){
    
         $user = $this->Aa->user_for_token($this);
        if (!$user) {   //If not a valid user
            return;
        }

        $menu = $this->GridButtons->returnButtons($user, false, 'ApProfiles'); 
        $this->set(array(
            'items' => $menu,
            'success' => true,
            '_serialize' => array('items', 'success')
        ));
    }
    
    public function menuForEntriesGrid(){
    
        $user = $this->Aa->user_for_token($this);
        if (!$user) {   //If not a valid user
            return;
        }

        $menu = $this->GridButtons->returnButtons($user, false, 'ApProfileEntries'); 
        $this->set(array(
            'items' => $menu,
            'success' => true,
            '_serialize' => array('items', 'success')
        ));
    }
    
    public function menuForExitsGrid(){
    
        $user = $this->Aa->user_for_token($this);
        if (!$user) {   //If not a valid user
            return;
        }

        $menu = $this->GridButtons->returnButtons($user, false, 'ApProfileExits'); 
        $this->set(array(
            'items' => $menu,
            'success' => true,
            '_serialize' => array('items', 'success')
        ));
    }
    
    
    public function menuForApsGrid(){
    
        $user = $this->Aa->user_for_token($this);
        if (!$user) {   //If not a valid user
            return;
        }

        $menu = $this->GridButtons->returnButtons($user, false, 'Aps'); 
        $this->set(array(
            'items' => $menu,
            'success' => true,
            '_serialize' => array('items', 'success')
        ));
    }
    
    public function menuForDevicesGrid(){
    
        $user = $this->Aa->user_for_token($this);
        if (!$user) {   //If not a valid user
            return;
        }

        $menu = $this->GridButtons->returnButtons($user, false, 'ApProfileDevices'); 
        $this->set(array(
            'items' => $menu,
            'success' => true,
            '_serialize' => array('items', 'success')
        ));
    }
    
    private function _add_dynamic($dc_data){
    
        //--Formulate a name
        $dc_data['name'] = 'APdesk_'.$dc_data['nasidentifier'];
        $dynClientEntity = $this->DynamicClients->newEntity($dc_data);
        if ($this->DynamicClients->save($dynClientEntity)) {
            //After this we can add the Realms if there are any
            $new_id = $dynClientEntity->id;
            $pieces = explode(",", $dc_data['realm_list']);
            foreach($pieces as $p){
                if(is_numeric($p)){
                    $dcr = [];
                    $dcr['dynamic_client_id'] = $new_id;
                    $dcr['realm_id'] = $p;

                    $dynClientRealmEntity = $this->DynamicClientRealms->newEntity($dcr);
                    $this->DynamicClientRealms->save($dynClientRealmEntity);
                }
            }   
        }
    }
    
    private function _add_dynamic_pair($nas_data){
        $data = [];
        $data['name']               = 'nasid';
        $data['value']              = $nas_data['nasidentifier'];
        $data['dynamic_detail_id']  = $nas_data['dynamic_detail_id'];
        $data['priority']           = 1;

        $dynPairEntity = $this->DynamicPairs->newEntity($data);
        $this->DynamicPairs->save($dynPairEntity);
    }
    
    private function _change_dynamic_shortname($ap_profile_name,$old_name,$new_name){ 
        $search_for = $ap_profile_name.'_'.$old_name.'_cp_';

        $q_r = $this->DynamicClients->find()->where(['DynamicClients.nasidentifier LIKE' => "$search_for%"])->all();

        foreach($q_r as $n){
            $current_name   = $n->nasidentifier;
            $id             = $n->id;
            $newname        = str_replace("$old_name","$new_name","$current_name");
            $d              = [];
            $d['id']        = $id;
            $d['nasidentifier'] = $newname;

            $dynClientEntity = $this->DynamicClients->newEntity($d);
            $this->DynamicClients->save($dynClientEntity);
        }
    }
    
     private function _check_if_available($openvpn_server_id,$ip){
        $count = $this->OpenvpnServerClients->find()->where([
            'OpenvpnServerClients.openvpn_server_id' => $openvpn_server_id,
            'OpenvpnServerClients.ip_address' => $ip,
        ])->count();

        if($count == 0){
            return true;
        }else{
            return false;
        }
    }

    private function _get_next_ip($ip){
        $pieces     = explode('.',$ip);
        $octet_1    = $pieces[0];
        $octet_2    = $pieces[1];
        $octet_3    = $pieces[2];
        $octet_4    = $pieces[3];

        if($octet_4 >= 254){
            $octet_4 = 1;
            $octet_3 = $octet_3 +1;
        }else{

            $octet_4 = $octet_4 +1;
        }
        $next_ip = $octet_1.'.'.$octet_2.'.'.$octet_3.'.'.$octet_4;
        return $next_ip;
    }
    
    private function _get_radio_for($hardware,$frequency){
        $q_e = $this->{'Hardwares'}->find()->where(['Hardwares.fw_id' => $hardware])->contain(['HardwareRadios'])->first();         
        if($q_e){
            foreach($q_e->hardware_radios as $hr){    
                $radio_number   = $hr->radio_number;
                if(($frequency == 5)&&(($hr->hwmode == '11a')||($hr->hwmode == '11a_ac'))){
                    return $hr->radio_number;
                }
                if(($frequency == 2.4)&&($hr->hwmode == '11g')){
                    return $hr->radio_number;
                }     
            }
        }    
        return 0;//Default = radio0;
    }
    
}
