<?php
 
    $json = '{   
        "requestId":"00df0e82-830f-40ce-b7a2-5f5700eda376",
        "responseCode":0,
        "responseMessage":"Success",
        "transactionId":"282090702",
        "transactionDate":"2022-06-26T14:12:28.9487702+02:00",
        "amount":450,
        "changeVoucher":{"pin":"",
                    "serialNumber":"20220626141280504499",
                    "expiryDate":"2025-06-26T14:12:28+02:00",
                    "amount":4100
                },
        "acquirer":{"account":
                {"accountNumber":
                "0310-6795-4489-9324"},
                "entityTag":"AMA-9678-01"
            }
    }';

  var_dump($json);
  print "<hr>";
  $data_obj = json_decode($json);
  var_dump($data_obj);
  print "<hr>";
  $data_arr = json_decode($json,true);
  var_dump($data_arr);
  print "<hr>";
  print "Results <br>";
  $changePin = $data_obj->changeVoucher;
  var_dump($changePin);
  echo $changePin->serialNumber;

?>