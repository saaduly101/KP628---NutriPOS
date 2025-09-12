<?php
// Check if 'order' parameter exists
if (isset($_GET['order'])) {
    $order = $_GET['order'];
    
    echo "<h1>Order Information:</h1>";

    if ($order == 'latest') {
        echo /*html*/"This is the latest order.";
    } else if ($order == 'select') {
        echo /*html*/"<a href='?order=1'>Order 1</a><br>";
        echo /*html*/"<a href='?order=2'>Order 2</a><br>";
        echo /*html*/"<a href='?order=3'>Order 3</a><br>";
    } else {
        echo $order;
    }
} else {
    // Default page when no 'order' parameter is provided
    echo /*html*/"<h1>Select an Order:</h1>";
    echo /*html*/"<a href='?order=latest'>Latest Order</a><br>";
    echo /*html*/"<a href='?order=select'>Other Orders</a><br>";
}
?>
