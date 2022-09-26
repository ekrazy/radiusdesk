#! /usr/bin/perl -w
use strict;
use POSIX;
use warnings;
use DateTime;
use Data::Dumper;
use feature 'say';

# use ...
# This is very important !
use vars qw(%RAD_REQUEST %RAD_REPLY %RAD_CHECK);
use constant RLM_MODULE_OK=> 2; # /* the module is OK,continue */
use constant RLM_MODULE_USERLOCK=>  5;#  /* reject the request (user is locked out) */
use constant RLM_MODULE_NOOP=> 7;
use constant RLM_MODULE_UPDATED=> 8; # /* OK (pairs modified) */

my $default_tz  = 'Africa/Johannesburg';
my $return      = RLM_MODULE_NOOP;
my $cut_off     = 60;
my $dt;


sub authorize {

    #Set the Timezone if it was set by the code called prior to when this was called 
    if(exists($RAD_CHECK{'Rd-Client-Timezone'})){   
        if($RAD_CHECK{'Rd-Client-Timezone'} ne 'timezone_not_found'){
            $default_tz = $RAD_CHECK{'Rd-Client-Timezone'};
        } 
    }
       
    if(exists($RAD_CHECK{'Login-Time'})){
        my $login_time  = $RAD_CHECK{'Login-Time'};
        do_logintime($login_time);
        if($return == RLM_MODULE_USERLOCK){
            $RAD_REPLY{'Reply-Message'} = "Not Available To Use On ".$dt->day_name." at ".$dt->hms(':');
        }     
    }
    return $return;
}

sub do_logintime {

    my($login_time) = @_;  
    my $session_found  = 0;
    $dt             = DateTime->now( time_zone => $default_tz);
    my $dt_start    = DateTime->now( time_zone => $default_tz);
    my $dt_end      = DateTime->now( time_zone => $default_tz);

    
    if(exists($RAD_REPLY{'Session-Timeout'})){
        $session_found = 1;   
    }

	
	my @spl         = split(/,|\|/, $login_time);
	my @days        = ();
	my @week_days   = ('Mo','Tu','We','Th','Fr');
	
	$return         = RLM_MODULE_USERLOCK; #Default is to reject if Login-Time is specified

	foreach my $i (@spl){
        if($i =~ m/^(Wk|Mo|Tu|We|Th|Fr|Sa|Su|Any|Al)/){
            my $j = $i;
            say $i;
            if($j =~ m/(Wk|Mo|Tu|We|Th|Fr|Sa|Su|Any|Al)([0-9]{4}-[0-9]{4})/){ #This is a match for a defined slot
                say "$1 is the Day - Got Time Span $2";
                my $span = $2;
                my ($span_start,$span_end) = split("-",$span);            
                my $span_start_hour = substr($span_start, 0, 2);
                my $span_start_min  = substr($span_start, 2, 4);
                my $span_end_hour = substr($span_end, 0, 2);
                my $span_end_min  = substr($span_end, 2, 4);
                #The span can be [high number]-[low number] OR [low number]-[high number]
                if($span_end_hour < $span_start_hour){
                    ($span_start_hour, $span_end_hour) = ($span_end_hour, $span_start_hour); #Swap them
                    ($span_start_min, $span_end_min) = ($span_end_min, $span_start_min); #Swap them   
                }
                #See if we fall in the span (Do not care about the day for now)

                $dt_start->set_hour($span_start_hour);
                $dt_start->set_minute($span_start_min);
                $dt_start->set_second(00);
                $dt_end->set_hour($span_end_hour);
                $dt_end->set_minute($span_end_min);
                $dt_end->set_second(00);
                
                say $dt_end->hms;
                say $dt_start->hms;
                push(@days, $1);            
                if(($dt->epoch >= $dt_start->epoch)&&($dt->epoch <= $dt_end->epoch)){
                    #Slot is correct - Lets find out the day
                    my $now_day = substr($dt->day_name,0,2);
                    foreach my $day (@days){
                        say $day;
                        if(($now_day eq $day)||($day eq 'Any')||($day eq 'Al')){
                            say "IN THE SLOT CALCULATE THE RETURN ".$dt_end->hms." ".$dt->hms;
                            my $s1 = $dt_end->epoch - $dt->epoch;
                            if($s1 <= $cut_off){ #No small values
                                $return = RLM_MODULE_USERLOCK;
                                return;
                            }
                            if($session_found){
                                if($RAD_REPLY{'Session-Timeout'} >= $s1){ #Adjust it only if smaller 
                                    $RAD_REPLY{'Session-Timeout'} = $s1;
                                    $return = RLM_MODULE_UPDATED;
                                }
                            }else{
                                $session_found = 1;
                                $RAD_REPLY{'Session-Timeout'} = $s1;
                                $return = RLM_MODULE_UPDATED;
                            }
                        }
                        if(($day eq 'Wk')&&((grep { $_ eq $now_day } @week_days))){
                            say "IN THE SLOT CALCULATE THE RETURNZZZ ".$dt_end->hms." ".$dt->hms;
                            my $s2 = $dt_end->epoch - $dt->epoch;
                            if($s2 <= $cut_off){ #No small values
                                $return = RLM_MODULE_USERLOCK;
                                return;
                            }
                            if($session_found){
                                if($RAD_REPLY{'Session-Timeout'} >= $s2){ #Adjust it only if smaller 
                                    $RAD_REPLY{'Session-Timeout'} = $s2;
                                    $return = RLM_MODULE_UPDATED;
                                }
                            }else{
                                $session_found = 1;
                                $RAD_REPLY{'Session-Timeout'} = $s2;
                                $return = RLM_MODULE_UPDATED;
                            }
                        }
                        #Not found in a slot
                        
                    }                   
                }
                @days = ();       
            }else{
                push(@days, $1);
            }
        }
    }
}

