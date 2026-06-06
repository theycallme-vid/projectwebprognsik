<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "charadrink";

$pesan = '';
try {
 $koneksi = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
 $koneksi->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
//  $pesan = "Koneksi berhasil<br/> 3";
} catch(PDOException $e) {
 $pesan = "Koneksi gagal: " . $e->getMessage();
}
?>