<?php

require '../../vendor/autoload.php';

use Dotenv\Dotenv;

// Load the .env file
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// Create Database Connection
$conn = mysqli_connect($_ENV['DB_SERVERNAME'], $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], $_ENV['DB_NAME']);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// echo /*html*/"
// <table>
//     <tr>
//         <th>Value</th>
//         <th>Time</th>
//     </tr>
//     <tr>
// ";
echo <<<'HTML'
<head>
    <style>
        table {
            /* width: 50%; */
            border-collapse: collapse;
            border:1px solid;
        }

        th, td {
            padding: 10px;
            /* border: 1px solid #000; */
            text-align: center;
            padding: 10px 30px;
        }

        tr {
            /* padding: 5px 15px; */
        }

        /* Alternating row colors */
        tr:nth-child(even) {
            background-color: #f2f2f2; /* Light gray */
        }

        tr:nth-child(odd) {
            background-color: #ffffff; /* White */
        }

        th {
            background-color: #c0d8ffff;
            /* color: white; */
        }
    </style>
</head>
<h1>Select an Order:</h1>
<table>
    <tr>
        <th>Date and Time</th>
        <th>Value</th>
    </tr>
HTML;

$result = $conn->query("SELECT * FROM orders ORDER BY `Timestamp` DESC");
while($row = $result->fetch_assoc()) {
    echo "<tr><td><a href='../order?order=" . $row['id'] . "'>" . $row['Timestamp'] . "</a></td><td>" . $row['Value'] . "</td></tr>";
}

echo <<<'HTML'
</table>
HTML;

// Close connection
mysqli_close($conn);
?>