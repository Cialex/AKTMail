<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="AktMail - Modern Web E-posta İstemcisi. Tüm e-postalarınızı tek bir yerden yönetin.">
    <title>AktMail - Modern Web E-posta İstemcisi</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            overflow-x: hidden;
        }

        .landing-container {
            max-width: 1200px;
            padding: 2rem;
            text-align: center;
        }

        .logo {
            font-size: 5rem;
            margin-bottom: 1rem;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-20px);
            }
        }

        h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            background: linear-gradient(to right, #fff, #e0e7ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .tagline {
            font-size: 1.5rem;
            margin-bottom: 3rem;
            opacity: 0.9;
            font-weight: 500;
        }

        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin: 3rem 0;
        }

        .feature {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 1rem;
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .feature:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .feature h3 {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
        }

        .feature p {
            opacity: 0.8;
            line-height: 1.6;
        }

        .cta-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 3rem;
        }

        .btn {
            padding: 1rem 2.5rem;
            font-size: 1.125rem;
            font-weight: 600;
            border: none;
            border-radius: 0.75rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #fff;
            color: #667eea;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
            border: 2px solid rgba(255, 255, 255, 0.5);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: #fff;
        }

        @media (max-width: 768px) {
            h1 {
                font-size: 2.5rem;
            }

            .tagline {
                font-size: 1.25rem;
            }

            .cta-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="landing-container">
        <div class="logo">📧</div>
        <h1>AktMail</h1>
        <p class="tagline">Tüm e-postalarınız tek bir yerde</p>

        <div class="features">
            <div class="feature">
                <div class="feature-icon">🔐</div>
                <h3>Güvenli</h3>
                <p>Gelişmiş şifreleme ve güvenlik önlemleri ile e-postalarınız güvende</p>
            </div>

            <div class="feature">
                <div class="feature-icon">⚡</div>
                <h3>Hızlı</h3>
                <p>Modern teknoloji ile yıldırım hızında e-posta yönetimi</p>
            </div>

            <div class="feature">
                <div class="feature-icon">📱</div>
                <h3>Responsive</h3>
                <p>Tüm cihazlarınızda mükemmel görünüm ve kullanım deneyimi</p>
            </div>

            <div class="feature">
                <div class="feature-icon">🎨</div>
                <h3>Modern Tasarım</h3>
                <p>Kullanıcı dostu ve şık arayüz ile keyifli deneyim</p>
            </div>

            <div class="feature">
                <div class="feature-icon">📁</div>
                <h3>Özel Klasörler</h3>
                <p>E-postalarınızı istediğiniz gibi organize edin</p>
            </div>

            <div class="feature">
                <div class="feature-icon">🔔</div>
                <h3>Anlık Bildirimler</h3>
                <p>Yeni e-postalardan anında haberdar olun</p>
            </div>
        </div>

        <div class="cta-buttons">
            <a href="login.php" class="btn btn-primary">Giriş Yap</a>
            <a href="register.php" class="btn btn-secondary">Kayıt Ol</a>
        </div>
    </div>
</body>

</html>