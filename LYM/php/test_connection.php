<?php
require_once '../includes/config.php';

try {
    $pdo = getDBConnection();
    echo "<h2>✅ Conexión a la base de datos exitosa</h2>";
    
    // Verificar si las tablas existen
    $tables = ['usuarios', 'productos', 'paquetes'];
    echo "<h3>Estado de las tablas:</h3>";
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "<p>✅ Tabla '$table' existe</p>";
            
            // Contar registros
            $countStmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $count = $countStmt->fetch()['count'];
            echo "<p>&nbsp;&nbsp;&nbsp;📊 Registros: $count</p>";
        } else {
            echo "<p>❌ Tabla '$table' no existe</p>";
        }
    }
    
    // Mostrar información de PHP
    echo "<h3>Información de PHP:</h3>";
    echo "<p>Versión de PHP: " . phpversion() . "</p>";
    echo "<p>Extensiones PDO disponibles: " . implode(', ', PDO::getAvailableDrivers()) . "</p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Error de conexión</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Asegúrate de que:</p>";
    echo "<ul>";
    echo "<li>XAMPP esté ejecutándose</li>";
    echo "<li>MySQL esté iniciado</li>";
    echo "<li>La base de datos 'nikenza_store' exista</li>";
    echo "<li>Las tablas hayan sido creadas ejecutando database.sql</li>";
    echo "</ul>";
}
?>

