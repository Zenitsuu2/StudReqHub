<?php
session_start();
include '../Connection/database.php'; // Keeping your include

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST["username"];
    $password = $_POST["password"];

    // Hard-coded credentials:
    if ($username === 'admin' && $password === 'admin123') {
        $_SESSION['admin'] = true;
        header('Location: admin_dashboard.php');
        exit();
    } else {
        $loginError = "Invalid username or password!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Poppins', sans-serif;
            background: url('../image/admin_picture.jpg') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            position: relative;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(0,0,0,0.7) 0%, rgba(0,0,0,0.5) 100%);
            z-index: 1;
        }
        
        .login-container {
            position: relative;
            z-index: 10;
            backdrop-filter: blur(5px);
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0% {
                transform: translateY(0px);
                box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            }
            50% {
                transform: translateY(-10px);
                box-shadow: 0 25px 40px rgba(0, 0, 0, 0.3);
            }
            100% {
                transform: translateY(0px);
                box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        @keyframes glowing {
            0% { box-shadow: 0 0 5px rgba(26, 42, 108, 0.5); }
            50% { box-shadow: 0 0 20px rgba(26, 42, 108, 0.8); }
            100% { box-shadow: 0 0 5px rgba(26, 42, 108, 0.5); }
        }
        
        .fade-in {
            animation: fadeIn 0.6s ease-out forwards;
        }
        
        .slide-in {
            animation: slideIn 0.5s ease-out forwards;
        }
        
        .input-field {
            transition: all 0.3s ease;
            border: 2px solid transparent;
            background: rgba(255, 255, 255, 0.9);
        }
        
        .input-field:focus {
            transform: translateY(-2px);
            border-color: #1a2a6c;
            background: rgba(255, 255, 255, 1);
            box-shadow: 0 10px 20px rgba(26, 42, 108, 0.2);
        }
        
        .login-btn {
            background: linear-gradient(135deg, #1a2a6c 0%, #b21f1f 100%);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .login-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(178, 31, 31, 0.3);
            animation: glowing 2s infinite;
        }
        
        .login-btn::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: 0.5s;
        }
        
        .login-btn:hover::after {
            left: 100%;
        }
        
        .input-icon {
            transition: all 0.3s ease;
        }
        
        .input-group:focus-within .input-icon {
            color: #1a2a6c;
            transform: scale(1.1);
        }
        
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-direction: column; /* Change to column layout */
            text-align: center; /* Center the text */
        }

        .logo-container img {
            width: 125px;
            height:125px;
            object-fit: contain;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
            transition: transform 0.3s ease;
            margin-bottom: 1rem; /* Add space between logo and text */
        }

        .logo-text {
            width: 100%; /* Full width for text container */
        }

        .logo-text h1 {
            font-size: 1.875rem; /* Adjust font size if needed (30px) */
            line-height: 2.25rem;
            white-space: normal; /* Allow text to wrap */
            word-wrap: break-word;
        }

        .h1{

                      
        }
    </style>
</head>
<body class="flex items-center justify-center">
    <div id="particles-js" class="particles"></div>
    
    <!-- Login Container -->
    <div class="login-container bg-black bg-opacity-70 p-10 rounded-3xl shadow-2xl w-full max-w-md">
        <!-- Logo/Brand -->
        <div class="flex items-center justify-center mb-8 fade-in" style="animation-delay: 0.1s;">
            <div class="logo-container">
                <img src="../image/logo_no_bg.png" alt="Logo" class="w-20 h-20 mr-4">
                <div class="logo-text">
                    <h1 class="text-white text-3xl font-bold tracking-wider">StudentRequestHub</h1>
                    <p class="text-gray-300 mt-2 italic">Admin Portal</p>
                </div>
            </div>
        </div>
        
        <!-- Login Form -->
        <form method="POST" class="space-y-6">
            <!-- Username Field -->
            <div class="input-group slide-in" style="animation-delay: 0.3s;">
                <label class="block text-gray-300 text-sm font-medium mb-2">Username</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3">
                        <i class="fas fa-user input-icon text-gray-500"></i>
                    </div>
                    <input 
                        type="text" 
                        name="username" 
                        class="input-field pl-10 w-full py-4 px-4 rounded-lg focus:outline-none" 
                        placeholder="Enter your username" 
                        required
                    >
                </div>
            </div>
            
            <!-- Password Field -->
            <div class="input-group slide-in" style="animation-delay: 0.5s;">
                <label class="block text-gray-300 text-sm font-medium mb-2">Password</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3">
                        <i class="fas fa-lock input-icon text-gray-500"></i>
                    </div>
                    <input 
                        type="password" 
                        name="password" 
                        class="input-field pl-10 w-full py-4 px-4 rounded-lg focus:outline-none" 
                        placeholder="Enter your password" 
                        required
                    >
                    <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                        <i class="fas fa-eye text-gray-500 cursor-pointer toggle-password"></i>
                    </div>
                </div>
            </div>
            
            <!-- Remember & Forgot -->
            <div class="flex justify-between items-center text-sm slide-in" style="animation-delay: 0.6s;">
                <div class="flex items-center">
                    <input type="checkbox" id="remember" class="mr-2 h-4 w-4">
                    <label for="remember" class="text-gray-300">Remember me</label>
                </div>
                <a href="#" class="text-blue-400 hover:text-blue-300 transition-colors"></a>
            </div>
            
            <!-- Login Button -->
            <div class="slide-in" style="animation-delay: 0.7s;">
                <button type="submit" class="login-btn w-full py-4 text-white font-semibold rounded-lg text-lg">
                    LOGIN
                </button>
            </div>
        </form>
        
        <!-- Footer -->
        <div class="text-center mt-8 text-gray-400 text-sm fade-in" style="animation-delay: 0.9s;">
            <p>© 2025 Admin Portal. All rights reserved.</p>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/particles.js/2.0.0/particles.min.js"></script>
    <script>
        // Initialize particles background
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof particlesJS !== 'undefined') {
                particlesJS('particles-js', {
                    "particles": {
                        "number": {
                            "value": 50,
                            "density": {
                                "enable": true,
                                "value_area": 800
                            }
                        },
                        "color": {
                            "value": "#ffffff"
                        },
                        "shape": {
                            "type": "circle",
                            "stroke": {
                                "width": 0,
                                "color": "#000000"
                            },
                        },
                        "opacity": {
                            "value": 0.5,
                            "random": true,
                            "anim": {
                                "enable": true,
                                "speed": 1,
                                "opacity_min": 0.1,
                                "sync": false
                            }
                        },
                        "size": {
                            "value": 3,
                            "random": true,
                            "anim": {
                                "enable": true,
                                "speed": 2,
                                "size_min": 0.1,
                                "sync": false
                            }
                        },
                        "line_linked": {
                            "enable": true,
                            "distance": 150,
                            "color": "#ffffff",
                            "opacity": 0.4,
                            "width": 1
                        },
                        "move": {
                            "enable": true,
                            "speed": 1,
                            "direction": "none",
                            "random": true,
                            "straight": false,
                            "out_mode": "out",
                            "bounce": false,
                        }
                    },
                    "interactivity": {
                        "detect_on": "canvas",
                        "events": {
                            "onhover": {
                                "enable": true,
                                "mode": "grab"
                            },
                            "onclick": {
                                "enable": true,
                                "mode": "push"
                            },
                            "resize": true
                        },
                        "modes": {
                            "grab": {
                                "distance": 140,
                                "line_linked": {
                                    "opacity": 1
                                }
                            },
                            "push": {
                                "particles_nb": 4
                            }
                        }
                    },
                    "retina_detect": true
                });
            }
        });

        // Toggle password visibility
        document.addEventListener('DOMContentLoaded', function() {
            const togglePassword = document.querySelector('.toggle-password');
            const passwordInput = document.querySelector('input[name="password"]');
            
            if (togglePassword && passwordInput) {
                togglePassword.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    this.classList.toggle('fa-eye');
                    this.classList.toggle('fa-eye-slash');
                });
            }
        });

        // Show login error if exists
        <?php if (isset($loginError)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Authentication Failed',
                text: '<?php echo $loginError; ?>',
                confirmButtonColor: '#1a2a6c',
                background: 'rgba(255, 255, 255, 0.9)',
                backdrop: 'rgba(0, 0, 0, 0.4)',
                customClass: {
                    title: 'text-red-600',
                    confirmButton: 'bg-gradient-to-r from-blue-600 to-red-600'
                }
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>