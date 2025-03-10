<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - Restaurant Admin' : 'Restaurant Admin'; ?></title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
    :root {
        --primary-color: #4361ee;
        --secondary-color: #3f37c9;
        --accent-color: #4895ef;
        --success-color: #4cc9f0;
        --navbar-height: 70px;
        --sidebar-width: 280px;
    }

    body {
        min-height: 100vh;
        background: #f8f9fa;
        padding-top: var(--navbar-height);
    }

    .main-content {
        margin-left: var(--sidebar-width);
        transition: all 0.3s ease;
        padding: 1.5rem;
        min-height: calc(100vh - var(--navbar-height));
    }

    @media (max-width: 991.98px) {
        .main-content {
            margin-left: 0;
        }
    }

    .loading-spinner {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.8);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 9999;
    }

    /* Smooth Scrollbar */
    ::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }

    ::-webkit-scrollbar-track {
        background: #f1f1f1;
    }

    ::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 3px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }

    /* Card Styles */
    .card {
        border: none;
        box-shadow: 0 2px 15px rgba(0,0,0,0.05);
        border-radius: 12px;
    }

    .card-header {
        background-color: #fff;
        border-bottom: 1px solid rgba(0,0,0,0.05);
    }

    /* Button Styles */
    .btn {
        border-radius: 8px;
        padding: 0.5rem 1rem;
        font-weight: 500;
    }

    .btn-primary {
        background: var(--primary-color);
        border-color: var(--primary-color);
    }

    .btn-primary:hover {
        background: var(--secondary-color);
        border-color: var(--secondary-color);
    }

    /* Form Controls */
    .form-control {
        border-radius: 8px;
        padding: 0.6rem 1rem;
        border: 1px solid rgba(0,0,0,0.1);
    }

    .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.15);
    }
    </style>

    <!-- Page Specific CSS -->
    <?php if (isset($extra_css)) echo $extra_css; ?>
</head>
<body>
    <!-- Loading Spinner -->
    <div class="loading-spinner" id="loadingSpinner">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- Include Navbar -->
    <?php include 'navbar.php'; ?>

    <!-- Include Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <?php if (isset($content)) echo $content; ?>
    </main>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Loading Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Hide loading spinner when page is loaded
        document.getElementById('loadingSpinner').style.display = 'none';

        // Handle sidebar toggle
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const mainContent = document.querySelector('.main-content');

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('show');
                if (window.innerWidth > 991.98) {
                    mainContent.style.marginLeft = sidebar.classList.contains('show') ? '0' : 'var(--sidebar-width)';
                }
            });
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth < 992) {
                if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 992) {
                sidebar.classList.remove('show');
                mainContent.style.marginLeft = 'var(--sidebar-width)';
            } else {
                mainContent.style.marginLeft = '0';
            }
        });
    });

    // Show loading spinner when navigating
    document.addEventListener('click', function(e) {
        if (e.target.tagName === 'A' && !e.target.hasAttribute('data-bs-toggle')) {
            document.getElementById('loadingSpinner').style.display = 'flex';
        }
    });
    </script>

    <!-- Page Specific Scripts -->
    <?php if (isset($extra_js)) echo $extra_js; ?>
</body>
</html> 