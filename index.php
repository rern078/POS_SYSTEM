<?php
session_start();
require_once 'config/database.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
      if ($_SESSION['role'] === 'admin') {
            header('Location: admin/index.php');
      } else {
            header('Location: user/index.php');
      }
      exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>POS System - Modern Point of Sale Solution</title>

      <!-- Bootstrap 5 CSS -->
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <!-- Font Awesome -->
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
      <!-- Google Fonts -->
      <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
      <!-- Main CSS -->
      <link rel="stylesheet" href="assets/css/main.css">

</head>

<body>
      <!-- Navigation -->
      <nav class="navbar navbar-expand-lg navbar-light fixed-top">
            <div class="container">
                  <a class="navbar-brand" href="#">
                        <i class="fas fa-store text-primary me-2"></i>POS System
                  </a>

                  <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                        <span class="navbar-toggler-icon"></span>
                  </button>

                  <div class="collapse navbar-collapse" id="navbarNav">
                        <ul class="navbar-nav ms-auto">
                              <li class="nav-item">
                                    <a class="nav-link" href="#features">Features</a>
                              </li>
                              <li class="nav-item">
                                    <a class="nav-link" href="#about">About</a>
                              </li>
                              <li class="nav-item">
                                    <a class="nav-link" href="#contact">Contact</a>
                              </li>
                              <li class="nav-item">
                                    <a class="nav-link btn btn-outline-primary btn-custom ms-2" href="login.php">
                                          <i class="fas fa-sign-in-alt me-2"></i>Login
                                    </a>
                              </li>
                              <li class="nav-item">
                                    <a class="nav-link btn btn-primary btn-custom ms-2" href="register.php">
                                          <i class="fas fa-user-plus me-2"></i>Register
                                    </a>
                              </li>
                        </ul>
                  </div>
            </div>
      </nav>

      <!-- Hero Section -->
      <section class="hero-section">
            <div class="floating-shapes">
                  <div class="shape"></div>
                  <div class="shape"></div>
                  <div class="shape"></div>
            </div>
            <div class="container">
                  <div class="row align-items-center hero-content">
                        <div class="col-lg-6">
                              <h1 class="display-4 fw-bold mb-4">
                                    Modern Point of Sale System
                              </h1>
                              <p class="lead mb-4">
                                    Streamline your business operations with our comprehensive POS solution.
                                    Manage sales, inventory, and customers with ease.
                              </p>
                              <div class="d-flex flex-wrap gap-3">
                                    <a href="register.php" class="btn btn-light btn-custom">
                                          <i class="fas fa-rocket me-2"></i>Get Started
                                    </a>
                                    <a href="#features" class="btn btn-outline-light btn-custom">
                                          <i class="fas fa-play me-2"></i>Learn More
                                    </a>
                              </div>
                        </div>
                        <div class="col-lg-6 text-center">
                              <div class="position-relative">
                                    <i class="fas fa-cash-register" style="font-size: 15rem; opacity: 0.3;"></i>
                              </div>
                        </div>
                  </div>
            </div>
      </section>

      <!-- Features Section -->
      <section id="features" class="py-5">
            <div class="container">
                  <div class="row text-center mb-5">
                        <div class="col-lg-8 mx-auto">
                              <h2 class="display-5 fw-bold mb-3">Why Choose Our POS System?</h2>
                              <p class="lead text-muted">
                                    Powerful features designed to help your business grow and succeed
                              </p>
                        </div>
                  </div>

                  <div class="row g-4">
                        <div class="col-lg-4 col-md-6">
                              <div class="card feature-card">
                                    <div class="card-body text-center">
                                          <div class="feature-icon bg-primary text-white">
                                                <i class="fas fa-cash-register"></i>
                                          </div>
                                          <h5 class="card-title">Easy Sales Management</h5>
                                          <p class="card-text">
                                                Process transactions quickly and efficiently with our intuitive interface.
                                                Support for multiple payment methods and receipt printing.
                                          </p>
                                    </div>
                              </div>
                        </div>

                        <div class="col-lg-4 col-md-6">
                              <div class="card feature-card">
                                    <div class="card-body text-center">
                                          <div class="feature-icon bg-success text-white">
                                                <i class="fas fa-box"></i>
                                          </div>
                                          <h5 class="card-title">Inventory Control</h5>
                                          <p class="card-text">
                                                Keep track of your stock levels in real-time. Get alerts for low inventory
                                                and manage product categories efficiently.
                                          </p>
                                    </div>
                              </div>
                        </div>

                        <div class="col-lg-4 col-md-6">
                              <div class="card feature-card">
                                    <div class="card-body text-center">
                                          <div class="feature-icon bg-info text-white">
                                                <i class="fas fa-chart-line"></i>
                                          </div>
                                          <h5 class="card-title">Analytics & Reports</h5>
                                          <p class="card-text">
                                                Generate detailed reports on sales, inventory, and customer behavior.
                                                Make data-driven decisions to grow your business.
                                          </p>
                                    </div>
                              </div>
                        </div>

                        <div class="col-lg-4 col-md-6">
                              <div class="card feature-card">
                                    <div class="card-body text-center">
                                          <div class="feature-icon bg-warning text-white">
                                                <i class="fas fa-users"></i>
                                          </div>
                                          <h5 class="card-title">User Management</h5>
                                          <p class="card-text">
                                                Manage multiple users with different access levels. Secure authentication
                                                and role-based permissions for your team.
                                          </p>
                                    </div>
                              </div>
                        </div>

                        <div class="col-lg-4 col-md-6">
                              <div class="card feature-card">
                                    <div class="card-body text-center">
                                          <div class="feature-icon bg-danger text-white">
                                                <i class="fas fa-mobile-alt"></i>
                                          </div>
                                          <h5 class="card-title">Mobile Responsive</h5>
                                          <p class="card-text">
                                                Access your POS system from any device. Works perfectly on desktop,
                                                tablet, and mobile devices.
                                          </p>
                                    </div>
                              </div>
                        </div>

                        <div class="col-lg-4 col-md-6">
                              <div class="card feature-card">
                                    <div class="card-body text-center">
                                          <div class="feature-icon bg-secondary text-white">
                                                <i class="fas fa-shield-alt"></i>
                                          </div>
                                          <h5 class="card-title">Secure & Reliable</h5>
                                          <p class="card-text">
                                                Enterprise-grade security with data encryption and regular backups.
                                                Your business data is safe with us.
                                          </p>
                                    </div>
                              </div>
                        </div>
                  </div>
            </div>
      </section>

      <!-- Stats Section -->
      <section class="stats-section">
            <div class="container">
                  <div class="row">
                        <div class="col-lg-3 col-md-6">
                              <div class="stat-card">
                                    <div class="stat-number">1000+</div>
                                    <p class="text-muted">Happy Customers</p>
                              </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                              <div class="stat-card">
                                    <div class="stat-number">50K+</div>
                                    <p class="text-muted">Transactions Processed</p>
                              </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                              <div class="stat-card">
                                    <div class="stat-number">99.9%</div>
                                    <p class="text-muted">Uptime Guarantee</p>
                              </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                              <div class="stat-card">
                                    <div class="stat-number">24/7</div>
                                    <p class="text-muted">Customer Support</p>
                              </div>
                        </div>
                  </div>
            </div>
      </section>

      <!-- CTA Section -->
      <section class="cta-section">
            <div class="container text-center">
                  <h2 class="display-5 fw-bold mb-4">Ready to Get Started?</h2>
                  <p class="lead mb-4">
                        Join thousands of businesses that trust our POS system to manage their operations.
                  </p>
                  <a href="register.php" class="btn btn-light btn-custom btn-lg">
                        <i class="fas fa-rocket me-2"></i>Start Your Free Trial
                  </a>
            </div>
      </section>

      <!-- Footer -->
      <footer class="footer">
            <div class="container">
                  <div class="row">
                        <div class="col-lg-4 mb-4">
                              <h5><i class="fas fa-store me-2"></i>POS System</h5>
                              <p class="text-muted">
                                    Modern point of sale solution designed to help businesses grow and succeed.
                                    Simple, powerful, and reliable.
                              </p>
                              <div class="social-links">
                                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                                    <a href="#"><i class="fab fa-twitter"></i></a>
                                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                                    <a href="#"><i class="fab fa-instagram"></i></a>
                              </div>
                        </div>

                        <div class="col-lg-2 col-md-6 mb-4">
                              <h5>Features</h5>
                              <ul class="list-unstyled">
                                    <li><a href="#">Sales Management</a></li>
                                    <li><a href="#">Inventory Control</a></li>
                                    <li><a href="#">Analytics</a></li>
                                    <li><a href="#">User Management</a></li>
                              </ul>
                        </div>

                        <div class="col-lg-2 col-md-6 mb-4">
                              <h5>Support</h5>
                              <ul class="list-unstyled">
                                    <li><a href="#">Help Center</a></li>
                                    <li><a href="#">Documentation</a></li>
                                    <li><a href="#">Contact Us</a></li>
                                    <li><a href="#">Status</a></li>
                              </ul>
                        </div>

                        <div class="col-lg-2 col-md-6 mb-4">
                              <h5>Company</h5>
                              <ul class="list-unstyled">
                                    <li><a href="#">About Us</a></li>
                                    <li><a href="#">Careers</a></li>
                                    <li><a href="#">Blog</a></li>
                                    <li><a href="#">Press</a></li>
                              </ul>
                        </div>

                        <div class="col-lg-2 col-md-6 mb-4">
                              <h5>Legal</h5>
                              <ul class="list-unstyled">
                                    <li><a href="#">Privacy Policy</a></li>
                                    <li><a href="#">Terms of Service</a></li>
                                    <li><a href="#">Cookie Policy</a></li>
                                    <li><a href="#">GDPR</a></li>
                              </ul>
                        </div>
                  </div>

                  <hr class="my-4">

                  <div class="row align-items-center">
                        <div class="col-md-6">
                              <p class="mb-0 text-muted">
                                    &copy; 2024 POS System. All rights reserved.
                              </p>
                        </div>
                        <div class="col-md-6 text-md-end">
                              <p class="mb-0 text-muted">
                                    Made with <i class="fas fa-heart text-danger"></i> for businesses
                              </p>
                        </div>
                  </div>
            </div>
      </footer>

      <!-- Bootstrap 5 JS -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

      <script>
            // Smooth scrolling for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                  anchor.addEventListener('click', function(e) {
                        e.preventDefault();
                        const target = document.querySelector(this.getAttribute('href'));
                        if (target) {
                              target.scrollIntoView({
                                    behavior: 'smooth',
                                    block: 'start'
                              });
                        }
                  });
            });

            // Navbar background change on scroll
            window.addEventListener('scroll', function() {
                  const navbar = document.querySelector('.navbar');
                  if (window.scrollY > 50) {
                        navbar.style.background = 'rgba(255,255,255,0.98)';
                  } else {
                        navbar.style.background = 'rgba(255,255,255,0.95)';
                  }
            });

            // Animate stats on scroll
            const observerOptions = {
                  threshold: 0.5
            };

            const observer = new IntersectionObserver(function(entries) {
                  entries.forEach(entry => {
                        if (entry.isIntersecting) {
                              entry.target.style.opacity = '1';
                              entry.target.style.transform = 'translateY(0)';
                        }
                  });
            }, observerOptions);

            document.querySelectorAll('.stat-number').forEach(stat => {
                  stat.style.opacity = '0';
                  stat.style.transform = 'translateY(20px)';
                  stat.style.transition = 'all 0.6s ease';
                  observer.observe(stat);
            });
      </script>
</body>

</html>