<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Paquete Viajero</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(to right, #e0f7fa, #ffffff);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        a.paquete-link {
            text-decoration: none;
            color: inherit;
        }

        .paquete-card {
            background-color: #ffffff;
            border-radius: 20px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
            max-width: 380px;
            padding: 30px 35px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .paquete-card:hover {
            transform: scale(1.04);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.15);
        }

        .paquete-header h3 {
            margin: 0;
            color: #1a3c40;
            font-size: 28px;
            margin-bottom: 12px;
        }

        .precio-paquete {
            font-size: 24px;
            font-weight: bold;
            color: #00897b;
            margin-bottom: 20px;
        }

        .paquete-content ul {
            list-style: none;
            padding: 0;
            margin: 0 0 25px 0;
        }

        .paquete-content li {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            color: #555;
        }

        .paquete-content li:last-child {
            border-bottom: none;
        }

        .ver-mas {
            margin-top: 20px;
            text-align: center;
            font-weight: bold;
            color: #00796b;
            font-size: 16px;
        }
    </style>
</head>

<body>

    <form action="https://www.paypal.com/cgi-bin/webscr" method="post" class="paquete-link">
        <input type="hidden" name="cmd" value="_xclick">
        <input type="hidden" name="business" value="TU_CORREO_PAYPAL@gmail.com">
        <input type="hidden" name="item_name" value="Paquete Viajero">
        <input type="hidden" name="amount" value="320">
        <input type="hidden" name="currency_code" value="MXN">

        <button type="submit" style="background:none; border:none; padding:0; width:100%; cursor:pointer;">
            <div class="paquete-card">
                <div class="paquete-header">
                    <h3>Paquete Viajero</h3>
                    <div class="precio-paquete">$320</div>
                </div>

                <div class="paquete-content">
                    <ul>
                        <li>1 Bolsa ecológica chica</li>
                        <li>2 Llaveros de acero con impresión frente y vuelta</li>
                        <li>1 Cojín 20 x 30 cm</li>
                        <li>Diseño personalizado a tu elección</li>
                    </ul>
                </div>

                <div class="ver-mas">Pagar ahora →</div>
            </div>
        </button>
    </form>


</body>

</html>