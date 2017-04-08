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

            if ($rs[0]["sp_crypto_validation"] === "PASSED"){ //The information pass all th verifications
                try {
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
 * @param $XML
 * @param $data
 * @param $conn
 * @return bool
 */
function insert_sale($XML, $data, &$conn){
    return true;
}

/** @author: @egonzalezm24
 *
 * @param $data
 * @return bool
 */
function make_ack($data){
    return false;
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