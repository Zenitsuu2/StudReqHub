<head>
    <!-- Add this in the head section if not already present -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Bootstrap JS is needed for the modal functionality -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- ...existing head content... -->
</head>

<div class="sidebar">
    <div class="sidebar-header">
        <div class="brand">
            <i class="fas fa-circle-user"></i>
            <div class="brand-text">
                <h3>Admin Portal</h3>
                <div class="user-status">
                    <span class="username">Admin User</span>
                    <span class="status"><i class="fas fa-circle"></i> Online</span>
                </div>
            </div>
        </div>
        <button class="close-btn">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <div class="sidebar-content">
        <div class="section-label">MAIN</div>
        <ul class="nav-menu">
            <li class="nav-item active">
                <a href="admin_dashboard.php">
                    <i class="fas fa-th-large"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <div class="section-label">MANAGEMENT</div>
            <li class="nav-item">
                <a href="history.php">
                    <i class="fas fa-history"></i>
                    <span>Request History</span>
                </a>
            </li>
           
            
            <li class="nav-item">
                <a href="chart.php">
                    <i class="fas fa-chart-line"></i>
                    <span>Chart & Analytics</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="activity_logs.php">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Activity Logs</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="calendar_activities.php">
                    <i class="fas fa-calendar"></i>
                    <span>Calendar of Activities</span>
                </a>
            </li>
        </ul>

        <div class="section-label">USERS</div>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="all_register.php">
                    <i class="fas fa-users"></i>
                    <span>Registered Students</span>
                </a>
            </li>
           
        </ul>

        <!-- About and Logout Section -->
        <div class="bottom-section">
            <div class="about-system">
                <button class="about-btn" onclick="showAboutModal()">
                    <i class="fas fa-info-circle"></i>
                    <span>About System</span>
                </button>
            </div>
            <div class="logout-section">
                <button class="logout-btn" onclick="confirmLogout()">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- About Modal -->
<div class="modal fade" id="aboutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">About The System</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <img src="../image/logo.jpg" alt="System Logo" class="img-fluid mb-3">
                    <h4 class="mt-2">StudentRequestHub</h4>
                    <p class="text-muted">Version 1.0</p>
                </div>
                <div class="developer-info">
                    <h5>Developers:</h5>
                    <ul class="list-unstyled">
                        <li>👨‍💻 Kenneth Fernandez</li>
                        <li>👨‍💻 Justine Manaloto</li>
                        <li>👨‍💻 Jordan Pangilinan</li>
                        <li>👩‍💻 Kathy Bagamasbad</li>
                        <li>👩‍💻 Samantha Dolleson</li>
                        <li>👩‍💻 Mackenzie Miana</li>
                        <li>👩‍💻 Ryza Cristobal</li>
                    </ul>
                    <p class="mt-3">
                        <strong>Technology Stack:</strong><br>
                        PHP, MySQL, JavaScript, Bootstrap, HTML, CSS, Word Press<br>
                    </p>
                    <p class="mt-3">
                        <strong>Released:</strong> 2025<br>
                        <strong>Institution:</strong> Clark College of Science and Technology<br>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    :root {
        --sidebar-bg: #1a1f36;
        --sidebar-hover: #252d47;
        --sidebar-active: #4264e8;
        --sidebar-text: #9197a3;
        --sidebar-text-active: #ffffff;
        --section-label: #5a6178;
        --online-status: #34d399;
        --sidebar-width: 240px;
    }

    .sidebar {
        width: var(--sidebar-width);
        height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        background-color: var(--sidebar-bg);
        color: var(--sidebar-text);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        transition: all 0.3s ease;
        z-index: 1000;
    }

    /* Header styles */
    .sidebar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .brand {
        display: flex;
        align-items: center;
    }

    .brand i {
        font-size: 24px;
        color: var(--sidebar-active);
        margin-right: 12px;
    }

    .brand-text h3 {
        color: var(--sidebar-text-active);
        margin: 0;
        font-size: 14px;
        font-weight: 600;
    }

    .user-status {
        display: flex;
        flex-direction: column;
        font-size: 12px;
    }

    .username {
        color: var(--sidebar-text);
        font-weight: 500;
    }

    .status {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .status i {
        font-size: 8px;
        color: var(--online-status);
    }

    .close-btn {
        background: transparent;
        border: none;
        color: var(--sidebar-text);
        cursor: pointer;
        padding: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Content styles */
    .sidebar-content {
        flex-grow: 1;
        overflow-y: auto;
        padding-top: 8px;
    }

    .section-label {
        padding: 16px 16px 8px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.7px;
        text-transform: uppercase;
        color: var(--section-label);
    }

    .nav-menu {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .nav-item {
        position: relative;
    }

    .nav-item a {
        display: flex;
        align-items: center;
        padding: 12px 16px;
        text-decoration: none;
        color: inherit;
        transition: all 0.2s ease;
    }

    .nav-item a i {
        min-width: 24px;
        font-size: 16px;
        margin-right: 12px;
        text-align: center;
    }

    .nav-item:hover a {
        background-color: var(--sidebar-hover);
        color: var(--sidebar-text-active);
    }

    .nav-item.active {
        background-color: var(--sidebar-hover);
    }

    .nav-item.active a {
        color: var(--sidebar-text-active);
        font-weight: 500;
    }

    .nav-item.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        height: 100%;
        width: 4px;
        background-color: var(--sidebar-active);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
        }
        
        .sidebar.show {
            transform: translateX(0);
        }
    }

    /* Animations for menu items */
    @keyframes fadeInLeft {
        from {
            opacity: 0;
            transform: translateX(-10px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .nav-item {
        animation: fadeInLeft 0.3s ease forwards;
        animation-delay: calc(var(--item-index) * 0.05s);
        opacity: 0;
    }

    .bottom-section {
        margin-top: auto;
        padding: 16px;
        border-top: 1px solid rgba(255, 255, 255, 0.05);
    }

    .about-btn,
    .logout-btn {
        width: 100%;
        display: flex;
        align-items: center;
        padding: 12px;
        border: none;
        background: transparent;
        color: var(--sidebar-text);
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .about-btn:hover,
    .logout-btn:hover {
        background-color: var(--sidebar-hover);
        color: var(--sidebar-text-active);
        border-radius: 6px;
    }

    .about-btn i,
    .logout-btn i {
        margin-right: 12px;
        font-size: 16px;
    }

    .logout-btn {
        margin-top: 8px;
        color: #ff6b6b;
    }

    .about-system {
        margin-bottom: 8px;
    }

    /* Modal Styles */
    .modal-content {
        border-radius: 12px;
    }

    /* Add these specific styles for the logo centering */
    .modal-body .text-center {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        width: 100%;
    }

    .modal-body .text-center img {
        max-width: 150px;
        height: auto;
        margin: 0 auto;
        display: block;
    }

    /* Override any potential conflicting styles */
    #aboutModal .modal-body img.img-fluid {
        margin-left: auto !important;
        margin-right: auto !important;
        display: block !important;
    }

    .developer-info {
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        text-align: left; /* Reset text alignment for content */
    }

    .developer-info li {
        margin-bottom: 8px;
        font-size: 15px;
    }
</style>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Set active menu item based on current page
        const currentPage = window.location.pathname.split("/").pop();
        const menuItems = document.querySelectorAll('.nav-item');
        
        // Reset active state first
        menuItems.forEach(item => item.classList.remove('active'));
        
        // Map pages to their menu items
        const pageMap = {
            'admin_dashboard.php': 0,
            'history.php': 1,
            'chart.php': 2,  
            'activity_logs.php': 3,
            'calendar_activities.php': 4,
            'all_register.php': 5
        };
        
        // Only add active class if the page is in our map
        if (pageMap.hasOwnProperty(currentPage)) {
            const index = pageMap[currentPage];
            if (index < menuItems.length) {
                menuItems[index].classList.add('active');
            }
        }
        
        // Add animation delay to each menu item
        menuItems.forEach((item, index) => {
            item.style.setProperty('--item-index', index);
        });
        
        // Toggle sidebar on mobile
        const closeBtn = document.querySelector('.close-btn');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                const sidebar = document.querySelector('.sidebar');
                sidebar.classList.toggle('show');
            });
        }
    });

    // Make sure Bootstrap is loaded before trying to use it
    function showAboutModal() {
        if (typeof bootstrap === 'undefined') {
            console.error('Bootstrap is not loaded. Please check your Bootstrap script inclusion.');
            alert('Could not show modal: Bootstrap is not loaded.');
            return;
        }
        
        const modalElement = document.getElementById('aboutModal');
        if (!modalElement) {
            console.error('Modal element not found');
            return;
        }
        
        let aboutModal;
        try {
            aboutModal = bootstrap.Modal.getInstance(modalElement);
            if (!aboutModal) {
                aboutModal = new bootstrap.Modal(modalElement);
            }
            aboutModal.show();
        } catch (error) {
            console.error('Error showing modal:', error);
            alert('Could not show modal. See console for details.');
        }
    }

    function confirmLogout() {
        if (typeof Swal === 'undefined') {
            console.error('SweetAlert2 is not loaded. Please check your SweetAlert2 script inclusion.');
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'login_admin.php';
            }
            return;
        }
        
        Swal.fire({
            title: 'Logout Confirmation',
            text: "Are you sure you want to logout?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, logout!',
            customClass: {
                confirmButton: 'btn btn-primary me-2',
                cancelButton: 'btn btn-danger'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Send AJAX request to logout
                if (typeof $ === 'undefined') {
                    console.error('jQuery is not loaded. Redirecting directly.');
                    window.location.href = 'login_admin.php';
                    return;
                }
                
                $.ajax({
                    url: 'logout_handler.php',
                    method: 'POST',
                    success: function() {
                        Swal.fire({
                            title: 'Logging Out',
                            text: 'You will be redirected to the login page.',
                            icon: 'success',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.href = 'login_admin.php';
                        });
                    },
                    error: function() {
                        Swal.fire({
                            title: 'Error',
                            text: 'Failed to logout. Please try again.',
                            icon: 'error'
                        });
                    }
                });
            }
        });
    }
</script>