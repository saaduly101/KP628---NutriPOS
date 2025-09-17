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
    <link rel="stylesheet" href="../public/style.css" />
    <!--
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
    -->
</head>
<body>
    <nav class="navbar">
      <div class="navbar-container">
        <div class="logo-dashboard"> 
          <a href="#" class="logo">NutriPOS</a>
          <span class="admin-dashboard">Admin Dashboard</span>
        </div>
        <ul class="navbar-links">
          <li><a href="../public/custom_pos_builder.html" class="nav-button">Menu Builder</a></li>
          <li><a href="../public/products.html" class="nav-button">Menu Management</a></li>
          <li><a href="../db/mysql_orders.php" class="nav-button active">Order History</a></li>
        </ul>
        <div class="user-section">
          <span class="admin"><?php echo htmlspecialchars($_SESSION['email']); ?></span>
          <a href="logout.php"><button class="logout-btn">Logout</button></a>
        </div>
      </div>
    </nav>
    <h2>Select an Order:</h2>
    <table>
       <tr>
            <th>ID</th>
            <th>Date and Time</th>
            <th>Value</th>
       </tr>
</body>
HTML;

$result = $conn->query("SELECT * FROM orders ORDER BY closed_at DESC");
while($row = $result->fetch_assoc()) {
    echo "
    <tr>
        <td><a href='../order.php?order=" . $row['id'] . "'>" . $row['id'] . "</a></td>
        <td><a href='../order.php?order=" . $row['id'] . "'>" . $row['closed_at'] . "</a></td>
        <td>" . $row['total'] . "</td>
    </tr>";
}

echo <<<'HTML'
</table>
HTML;

// Close connection
mysqli_close($conn);
?>