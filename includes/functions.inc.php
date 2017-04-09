<?php

/** @author: @egonzalezm24
 *
 * FIRST Validation
 * @param $data Object <b>JSON</b >Information retrieved from php://input
 * @param $conn pgsql_conn <b>Object</b> Active connection against PostgreSQL
 * @return bool Whether the information collected is valid or not
 */
function fst_validation($data, &$conn){
    $clave = $data["clave"];
    $fecha = $data["fecha"];
    $sucursal = $data["sucursal"];
    $emisor = $data["emisor"];
    $receptor = $data["receptor"];

    //Validate lengths
    if (strlen($clave) === 50 &&
        strlen($emisor["numeroIdentificacion"]) === 12 &&
        strlen($receptor["numeroIdentificacion"]) === 12 &&
        is_int($sucursal)){

        //Attempt to pass first validation against PostgreSQL
        $rs = $conn->execSQL("SELECT sp_fst_validation(?,?,?,?,?)",
            array($clave,
                $emisor["numeroIdentificacion"],
                $receptor["numeroIdentificacion"],
                $fecha,
                $sucursal),
            true);

        return ($rs[0]["sp_fst_validation"] === "PASSED");
    } else {
        return false;
    }
}

/** @author: @egonzalezm24
 *
 * SECOND Validation
 * @param $data Object <b>JSON</b >Information retrieved from php://input
 * @param $conn pgsql_conn <b>Object</b> Active connection against PostgreSQL
 * @param $gpg gnupg <b>Object</b> Interface between PHP and GPG
 * @return bool false if validation fails; plain <b>XML</b> if success
 */
function crypto_validation($data, &$conn, &$gpg){
    $signedXML = $data["comprobanteXml"]; //Signed and encrypted data
    try { //Attempt to verify
        $gpg -> adddecryptkey(mykey, mypass);
        $sign = $gpg->decryptverify($signedXML, null);
        $fingerprint = $sign[0]["fingerprint"];
        $author = $gpg -> keyinfo($fingerprint);
        $author = $author[0];
    } catch (Exception $ex){
        $gpg -> cleardecryptkeys();
        return false;
    }

    //Verify the validity of the sign
    if (!$author["disabled"] && !$author["expired"] && !$author["revoked"] && $sign != null){
        $author = $author["uids"][0];
        $author["ced"] = $data["emisor"]["numeroIdentificacion"]; //Load 'cedula' to author

        if (!$author["revoked"] && !$author["invalid"] && $author != null){
            //Pass crypto validation against PostgreSQL
            $rs = $conn->execSQL("SELECT sp_crypto_validation(?,?)",
                array($fingerprint,
                    $author["ced"]),
                true);

            if ($rs[0]["sp_crypto_validation"] !== "NO"){ //The information pass all the verifications
                try {
                    define("pos_key", $rs[0]["sp_crypto_validation"]); //For encrypting ack later
                    $XML = $gpg -> decrypt($signedXML);
                    $gpg -> cleardecryptkeys();
                    return !$XML ? false : $XML ;
                } catch (Exception $ex){
                    $gpg -> cleardecryptkeys();
                    return false;
                }
            }
        }
    } else {
        $gpg -> cleardecryptkeys();
        return false;
    }
}

/** @author: Randall Castillo
 * THIRD Validation
 * @param $XML String XML string decrypted
 * @return bool Whether it pass the validation against XSD or not
 */
function xsd_validation($XML){
    //HERE YOU START TYPING, RANDALL...
    /*
     * If you need to save something, do it on ../includes/
     * As .inc.php snd then include it here.
     * */
    return true;
}

/** @author: @egonzalezm24
 *
 * @param $XML String XML string decrypted
 * @param $data Object <b>JSON</b >Information retrieved from php://input
 * @param $conn pgsql_conn <b>Object</b> Interface between PHP and GPG
 * @return bool Whether it inserted the sale or not
 */
function insert_sale($XML, $data, &$conn){
    $sucursal = $data["sucursal"];
    $ced = $data["emisor"]["numeroIdentificacion"];
    $clave = $data["clave"];
    $fecha = $data["fecha"];

    $rs = $conn->execSQL("SELECT sp_new_sale(?,?,?,?,?);",
        array($sucursal, $ced, $clave, $fecha, $XML),
        true);

    return ($rs[0]["sp_new_sale"] === "OK");
}

/**
 * @param $data Object <b>JSON</b >Information retrieved from php://input
 * @param $conn pgsql_conn <b>Object</b> Interface between PHP and GPG
 * @param $gpg gnupg <b>Object</b> Interface between PHP and GPG
 * @return bool Whether it sent the acknowledge of receipt or not
 */
function make_ack($data, &$conn, &$gpg){
    $gpg->addsignkey(mykey, mypass);
    $gpg -> addencryptkey(pos_key);
    $plain_XML = do_hash($data);
    $rs = $conn->execSQL("SELECT sp_insert_ack(?,?)",
        array($data["clave"],
            $plain_XML),
        true);
    if ($rs[0]["sp_insert_ack"] === "OK"){
        try {
            $signed_XML = $gpg -> encryptsign($plain_XML);
            echo json_encode(array(
                "clave" => $data["clave"],
                "fecha" => $data["fecha"],
                "indEstado" => "RECIBIDO",
                "respuestaXML" => $signed_XML
            ));
            $gpg -> clearencryptkeys();
            $gpg -> clearsignkeys();
            return true;
        } catch (Exception $ex){
            return false;
        }
    } else {
        return false;
    }
}

/**
 * @param $data Object <b>JSON</b >Information retrieved from php://input
 * @return string The XML ack
 */
function do_hash($data){
    $hashSrc = $data["clave"]."-".$data["receptor"]["numeroIdentificacion"]."-".microtime();
    $ack_XML = "<?xml version='1.0'?><acuse><ID>".hash("sha256", $hashSrc)."</ID><estado>RECIBIDO</estado></acuse>";
    return $ack_XML;
}

/** @author: York
 *
 * @param $status int HTTP Status
 * @param $status_message String HTTP Status Message
 * @param $data array Data to append
 * @return bool For callback purposes
 */
function deliver_response($status, $status_message,$data){
    header("HTTP/1.1 $status $status_message");

    $response["status"]=$status;
    $response["status_message"]=$status_message;
    $response["data"]=$data;

    $json_response=json_encode($response);
    echo $json_response;

    if ($status === 400){
        exit;
    }
}

/** --------------------
 * Created by PhpStorm.
 * User: Emilio
 * Date: 07/04/2017
 * Time: 10:50
----------------------*/