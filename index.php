<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$method = $_SERVER['REQUEST_METHOD'];

// Include your database connection
include_once $_SERVER['DOCUMENT_ROOT'] . '/conciergeLastVersion/config/db.php';
// include_once __DIR__ . '/api/apartment/create.php';



// Parse the action from the request
$action = $_GET['action'] ?? null;

switch ($method) {

    case 'POST':
        if ($action === 'login') {
            include 'api/auth/login.php';
        } else if ($action === 'register') {
            include 'api/auth/register.php';
        } else if ($action === 'create_service') {
            include 'api/services/create.php';
        } else if ($action === 'create_testimony'){
            include 'api/testimonies/create.php';
        } else if( $action === 'create_event' ){
            include 'api/events/create.php';
        } else if ($action === 'create_contact'){
            include 'api/contact/create.php';
        } else if($action === 'create_reservation'){
            include 'api/reservation/create.php';
        } else if($action === 'create_apartment'){
            include 'api/apartment/create.php';
        } else if($action === 'create_apartment_reservation'){
            include 'api/apartment_reservation/create_reservation.php';
        } else {
            echo json_encode(['message' => 'Invalid action']);
        }
      
        
        break;

    case 'GET':
        if ($action === 'read_service') {
            include 'api/services/read.php';
        } else if ($action === 'read_testimonies') {
            include 'api/testimonies/read.php';
        } else if ($action === 'read_events') {
            include 'api/events/read.php';
        } else if ($action === 'read_contact'){
            include 'api/contact/read.php';
        } else if($action === 'read_reservations'){
            include 'api/reservation/read.php';
        } else if($action === 'read_apartment'){
            include 'api/apartment/read.php';
        } else if($action === 'statistics'){
            include 'api/statistics/stats.php';
        } else if($action === 'getTitle_service'){
            include 'api/services/getTitle.php';
        } else if ($action === 'read_apartment_reservations'){
        include 'api/apartment_reservation/read_reservations.php';

        } else {
            echo json_encode(['message' => 'Invalid action']);
        }
        break;

    case 'PUT':
        if ($action === 'update_service') {
            include 'api/services/update.php';
        } else if ($action === 'update_testimony') {
            include 'api/testimonies/update.php';
        } else if ($action === 'update_event') {
            include 'api/events/update.php';
        } else if($action === 'update_reservation' ){
            include 'api/reservation/update.php';
        } else if($action === 'update_apartment'){
            include 'api/apartment/update.php';
        
        } else if($action === 'update_apartment_reservation') {
                include 'api/apartment_reservation/update_reservation.php';
        } else {
            echo json_encode(['message' => 'Invalid action']);
        }
        break;
        

    case 'DELETE':
        if ($action === 'delete_service') {
            include 'api/services/delete.php';
        } else if ($action === 'delete_testimony') {
            include 'api/testimonies/delete.php';
        } else if ($action === 'delete_event') {
            include 'api/events/delete.php';
        } else if($action === 'delete_apartment'){
            include 'api/apartment/delete.php';
        } else {
            echo json_encode(['message' => 'Invalid action']);
        }
        break;

    default:
        echo json_encode(['message' => 'Unsupported request method']);
        break;
}