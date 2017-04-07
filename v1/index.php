<?php
/*---------- PROD ----------*/
error_reporting(E_ERROR);
/*---------- PROD ----------*/

define("mykey", "01EDAD6FC4548BBB68861903777DD77062D75C9F");
define("mypass", "admin");
define("errno0", array("code" => "errno0", "text" => "La informacion remitida de Comprobante, Emisor o Receptor NO es correcta."));
define("errno1", array("code" => "errno1", "text" => "La informacion remitida no ha pasado la prueba de verificacion de firma digital y decriptacion."));
define("errno2", array("code" => "errno2", "text" => "El documento XML remitido no cumple todos los requisitos para su almacenaje."));
define("errno3", array("code" => "errno3", "text" => "El servidor no ha logrado registrar el comprobante. Contacte con la DGT."));
define("errno4", array("code" => "errno4", "text" => "El comprobante ha sido registrado, sin embargo, no se ha podido remitir el pertinente acuse de recibo. Espere por su arribo durante las siguientes 72 horas."));

include("../includes/pgsql_conn.inc.php"); //pgsql class
include("../includes/functions.inc.php"); //Control functions

header("Content-Type:application/json");
header("Accept:application/json");
$method = $_SERVER["REQUEST_METHOD"];

if ($method == "POST"){
    $conn = new pgsql_conn();
    putenv("GNUPGHOME=/home/dgt/.gnupg");
    $gpg = new gnupg();
    $gpg -> seterrormode(gnupg::ERROR_WARNING);

    if (!$conn->init()){
        /**
         * If you can not access your data, you cannot do anything
         */
        deliver_response(500, "Internal Server Error", array("text" => "Server not available"));
        exit;
    } else {
        /**
         * FIRST Validation, STEPS:
         *  1. (Done in fst_validation)- Verify lengths and validate against Database
         *  2. (Done in crypto_validation)- Verify digital signature
         *  2. (Done in crypto_validation)- Verify if it the data does belongs to who is sending it
         *  3. (Done in crypto_validation)- Decrypt information
         *
         * SECOND Validation. Once verified and decrypted, the plain XML is validated against the XSD Schema:
         * STEPS:
         *  1. (Done in xsd_validation)- Verify the plain XML
         *
         * STORE. After the last validation, the information is stored in the Database.
         *
         * RESPONSE. Once stored, the server shall make the pertinent ACK.
         */
        $data = file_get_contents("php://input");
        $data = json_decode($data, TRUE);

        $XML = fst_validation($data, $conn) ? crypto_validation($data, $conn, $gpg) : deliver_response(400, "Error", errno0);
        $passed_xsd = !$XML ? deliver_response(400, "Error", errno1) : xsd_validation($XML);
        $inserted_sale = $passed_xsd ? insert_sale($XML, $data, $conn) : deliver_response(400, "Error", errno2);
        $ack_sent = $inserted_sale ? make_ack($data) : deliver_response(500, "Internal Server Error", errno3);

        !$ack_sent ? deliver_response(500, "Internal Server Error", errno4) : exit;
    }
} else {
    header("HTTP/1.1 403 Forbidden");
}

/** --------------------
 * Created by PhpStorm.
 * User: Emilio
 * Date: 06/04/2017
 * Time: 8:30
----------------------*/