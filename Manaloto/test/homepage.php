<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentRequestHub - Home</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }

        header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 1rem;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-container {
            display: flex;
            align-items: center;
            cursor: pointer;
        }

        .site-title {
            font-size: 1.8rem;
            margin-left: 10px;
            font-weight: 600;
        }

        .logo {
            width: 60px;
            height: 60px;
            animation: rotate 10s linear infinite;
            transition: transform 0.3s ease;
        }

        .logo:hover {
            animation-play-state: paused;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        nav ul {
            display: flex;
            list-style: none;
        }

        nav ul li {
            margin-left: 1.5rem;
        }

        nav ul li a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
            padding: 0.5rem 0;
        }

        nav ul li a:hover {
            color: #f0f0f0;
        }

        nav ul li a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background-color: #ffffff;
            transition: width 0.3s ease;
        }

        nav ul li a:hover::after {
            width: 100%;
        }

        .active {
            font-weight: 600;
        }

        .active::after {
            width: 100% !important;
        }

        .login-btn {
            background-color: #ffffff;
            color: #1e3c72;
            padding: 0.5rem 1.2rem;
            border-radius: 4px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .login-btn:hover {
            background-color: #e6e6e6;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .hero {
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('pic/background.jpg') no-repeat center center;
            background-size: cover;
            height: 500px;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
            color: white;
        }

        .hero-content {
            max-width: 800px;
            padding: 0 20px;
        }

        .hero h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
            animation: fadeInUp 1s ease;
        }

        .hero p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            animation: fadeInUp 1s ease 0.2s;
            animation-fill-mode: both;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .cta-btn {
            background-color: #1e3c72;
            color: white;
            padding: 0.8rem 1.8rem;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
            animation: fadeInUp 1s ease 0.4s;
            animation-fill-mode: both;
        }

        .cta-btn:hover {
            background-color: #2a5298;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
        }

        .features {
            max-width: 1200px;
            margin: 4rem auto;
            padding: 0 20px;
        }

        .section-title {
            text-align: center;
            font-size: 2.2rem;
            margin-bottom: 2.5rem;
            position: relative;
            color: #1e3c72;
        }

        .section-title::after {
            content: '';
            position: absolute;
            width: 80px;
            height: 4px;
            background-color: #1e3c72;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            opacity: 0;
            transform: translateY(40px);
        }

        .feature-card.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
        }

        .feature-img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .feature-content {
            padding: 1.5rem;
        }

        .feature-content h3 {
            margin-bottom: 0.8rem;
            color: #1e3c72;
        }

        .about {
            background-color: #1e3c72;
            color: white;
            padding: 5rem 0;
        }

        .about-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 3rem;
            align-items: center;
        }

        .about-image {
            width: 100%;
            border-radius: 8px;
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);
        }

        .about-content h2 {
            font-size: 2.2rem;
            margin-bottom: 1.5rem;
        }

        .about-content p {
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
            line-height: 1.8;
        }

        footer {
            background-color: #141e30;
            color: white;
            padding: 3rem 0;
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
        }

        .footer-col h3 {
            margin-bottom: 1rem;
            position: relative;
            padding-bottom: 10px;
        }

        .footer-col h3::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 2px;
            background-color: #2a5298;
        }

        .footer-col ul {
            list-style: none;
        }

        .footer-col ul li {
            margin-bottom: 0.5rem;
        }

        .footer-col ul li a {
            color: #ccc;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .footer-col ul li a:hover {
            color: white;
            padding-left: 5px;
        }

        .copyright {
            text-align: center;
            padding-top: 2rem;
            margin-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 0.9rem;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                text-align: center;
            }

            nav ul {
                margin-top: 1rem;
                flex-wrap: wrap;
                justify-content: center;
            }

            nav ul li {
                margin: 0.5rem;
            }

            .hero h1 {
                font-size: 2.5rem;
            }

            .section-title {
                font-size: 1.8rem;
            }

            .about-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-container">
            <div class="logo-container" onclick="window.location.href='#'">
                <svg class="logo" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="50" cy="50" r="45" fill="#ffffff" />
                    <path d="M50 15 L85 50 L50 85 L15 50 Z" fill="#1e3c72" />
                    <circle cx="50" cy="50" r="10" fill="#ffffff" />
                </svg>
                <div class="site-title">StudentRequestHub</div>
            </div>
            <nav>
                <ul>
                    <li><a href="#" class="active">Home</a></li>
                    <li><a href="#features">Services</a></li>
                    <li><a href="#about">About</a></li>
                    <li><a href="#contact">Contact</a></li>
                    <li><a href="../../user/Loginpage.php" class="login-btn">Login</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <section class="hero">
        <div class="hero-content">
            <h1>Welcome to StudentRequestHub</h1>
            <p>Your one-stop platform for streamlined student requests and services management.</p>
            <a href="../../user/Loginpage.php" class="cta-btn">Get Started</a>
        </div>
    </section>

    <section class="features" id="features">
        <h2 class="section-title">Our Features</h2>
        <div class="features-grid">
            <div class="feature-card">
                <img src="../images/step2.png" alt="Easy Request Submission" class="feature-img">
                <div class="feature-content">
                    <h3>Easy Request Submission</h3>
                    <p>Submit your requests in minutes with our intuitive interface designed for students.</p>
                </div>
            </div>
            <div class="feature-card">
                <img src="../images/track.png" alt="Real-time Tracking" class="feature-img">
                <div class="feature-content">
                    <h3>Real-time Tracking</h3>
                    <p>Monitor your request status in real-time and get instant notifications on updates.</p>
                </div>
            </div>
            <div class="feature-card">
                <img src="../images/step4.png" alt="Secure Communication" class="feature-img">
                <div class="feature-content">
                    <h3>Secure Communication</h3>
                    <p>Communicate securely with administrators and staff through our encrypted messaging system.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="about" id="about">
        <div class="about-container">
            <div>
                <img src="../images/schoolbg01.jpg" alt="School campus" class="about-image">
            </div>
            <div class="about-content">
                <h2>About StudentRequestHub</h2>
                <p>StudentRequestHub is a comprehensive platform designed to simplify and streamline the request management process for educational institutions. Our system bridges the gap between students and administration, making it easier than ever to submit, track, and resolve various student requests.</p>
                <p>Founded with a mission to improve communication and efficiency in schools, our platform has been implemented in numerous educational institutions with outstanding results.</p>
            </div>
        </div>
    </section>

    <footer id="contact">
        <div class="footer-container">
            <div class="footer-col">
                <h3>StudentRequestHub</h3>
                <p>Making student request management simple, efficient, and transparent.</p>
            </div>
            <div class="footer-col">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="#">Home</a></li>
                    <li><a href="#features">Services</a></li>
                    <li><a href="#about">About Us</a></li>
                    <li><a href="#contact">Contact</a></li>
                    <li><a href="../../user/Loginpage.php">Login</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h3>Contact Us</h3>
                <ul>
                    <li>Email: info@studentrequesthub.com</li>
                    <li>Phone: +63 123 4567 089</li>
                    <li>Address: Villa Teodora Dau</li>
                </ul>
            </div>
           
        </div>
        <div class="copyright">
            &copy; <?php echo date('Y'); ?> StudentRequestHub. All rights reserved.
        </div>
    </footer>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Smooth scrolling for navigation links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('href');
                    if (targetId !== '#') {
                        const targetElement = document.querySelector(targetId);
                        if (targetElement) {
                            window.scrollTo({
                                top: targetElement.offsetTop - 100,
                                behavior: 'smooth'
                            });
                            
                            // Update active nav link
                            document.querySelectorAll('nav a').forEach(link => {
                                link.classList.remove('active');
                            });
                            this.classList.add('active');
                        }
                    }
                });
            });

            // Logo click handler
            document.querySelector('.logo-container').addEventListener('click', function() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
                document.querySelectorAll('nav a').forEach(link => {
                    link.classList.remove('active');
                });
                document.querySelector('nav a[href="#"]').classList.add('active');
            });

            // Feature card animation
            const featureCards = document.querySelectorAll('.feature-card');
            
            function checkVisibility() {
                featureCards.forEach((card, index) => {
                    const rect = card.getBoundingClientRect();
                    const isVisible = (rect.top <= window.innerHeight * 0.75);
                    
                    if (isVisible) {
                        setTimeout(() => {
                            card.classList.add('visible');
                        }, index * 150);
                    }
                });
            }
            
            // Initial check
            checkVisibility();
            
            // Check on scroll
            window.addEventListener('scroll', checkVisibility);
            
            // Set active nav link based on scroll position
            window.addEventListener('scroll', function() {
                const scrollPosition = window.scrollY;
                
                document.querySelectorAll('section').forEach(section => {
                    const sectionTop = section.offsetTop - 150;
                    const sectionBottom = sectionTop + section.offsetHeight;
                    const sectionId = section.getAttribute('id');
                    
                    if (scrollPosition >= sectionTop && scrollPosition < sectionBottom) {
                        document.querySelectorAll('nav a').forEach(link => {
                            link.classList.remove('active');
                            if (link.getAttribute('href') === `#${sectionId}`) {
                                link.classList.add('active');
                            }
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>