<?php
    
   include "config.php";
   session_start();
   echo "AMA-".time()."-".strtoupper(session_id());

?>