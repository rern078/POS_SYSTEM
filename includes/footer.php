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

<!-- Main Navigation JavaScript -->
<script src="assets/js/main.js"></script>

<script>
      let currentOrderId = null;

      // Cart Functions
      function openCart() {
            console.log('Opening cart...'); // Debug log

            const cartSidebar = document.getElementById('cartSidebar');
            const cartOverlay = document.getElementById('cartOverlay');

            if (!cartSidebar || !cartOverlay) {
                  console.error('Cart elements not found');
                  return;
            }

            cartSidebar.classList.add('open');
            cartOverlay.classList.add('show');

            // Ensure buttons are properly initialized
            const checkoutBtn = document.getElementById('checkoutBtn');
            const clearCartBtn = document.getElementById('clearCartBtn');

            if (checkoutBtn) {
                  checkoutBtn.disabled = false;
                  checkoutBtn.innerHTML = '<i class="fas fa-credit-card me-2"></i>Checkout';
            }

            // Fetch cart data
            fetchGuestCart();
      }

      function closeCart() {
            document.getElementById('cartSidebar').classList.remove('open');
            document.getElementById('cartOverlay').classList.remove('show');
      }

      function handleProductCardClick(productId) {
            // Check if quantity overlay is already visible
            const overlay = document.getElementById(`quantity-overlay-${productId}`);
            if (overlay && overlay.style.display === 'flex') {
                  return; // Don't do anything if overlay is already visible
            }

            // Show product detail modal instead of quantity overlay
            showProductDetailModal(productId);
      }

      function addToGuestCart(productId) {
            addToGuestCartWithQuantity(productId, 1);
      }

      function addToGuestCartWithQuantity(productId, quantity = null) {
            if (quantity === null) {
                  quantity = parseInt(document.getElementById(`quantity-${productId}`).textContent);
            }

            fetch('index.php', {
                        method: 'POST',
                        headers: {
                              'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `action=add_to_cart&product_id=${productId}&quantity=${quantity}`
                  })
                  .then(response => response.json())
                  .then(data => {
                        if (data.success) {
                              showNotification(`${quantity} item(s) added to cart!`, 'success');
                              updateCartBadge();
                              fetchGuestCart();
                              hideQuantityOverlay(productId);
                        } else {
                              showNotification(data.message, 'error');
                        }
                  })
                  .catch(error => {
                        showNotification('Error adding product to cart', 'error');
                  });
      }

      function addToCart(productId) {
            const quantity = 1;
            fetch('index.php', {
                        method: 'POST',
                        headers: {
                              'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `action=add_to_cart&product_id=${productId}&quantity=${quantity}`
                  })
                  .then(response => response.json())
                  .then(data => {
                        if (data.success) {
                              showNotification(`${quantity} item(s) added to cart!`, 'success');
                              updateCartBadge();
                              fetchGuestCart();
                        } else {
                              showNotification(data.message, 'error');
                        }
                  })
                  .catch(error => {
                        showNotification('Error adding product to cart', 'error');
                  });
      }

      function showQuantityOverlay(productId) {
            document.getElementById(`quantity-overlay-${productId}`).style.display = 'flex';
            document.getElementById(`quantity-${productId}`).textContent = '1';
      }

      function hideQuantityOverlay(productId) {
            document.getElementById(`quantity-overlay-${productId}`).style.display = 'none';
      }

      function updateProductQuantity(productId, change) {
            const quantityElement = document.getElementById(`quantity-${productId}`);
            let currentQuantity = parseInt(quantityElement.textContent);
            let newQuantity = currentQuantity + change;

            // Ensure quantity doesn't go below 1
            if (newQuantity < 1) {
                  newQuantity = 1;
            }

            quantityElement.textContent = newQuantity;
      }

      // Add keyboard support for quantity overlay
      document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                  // Close all quantity overlays when Escape is pressed
                  document.querySelectorAll('.quantity-overlay').forEach(overlay => {
                        overlay.style.display = 'none';
                  });
            }
      });

      function updateGuestQuantity(productId, change) {
            const cartItem = document.querySelector(`#cart-item-${productId}`);
            const currentQty = parseInt(cartItem.querySelector('.cart-qty-input').value);
            const stockQty = parseInt(cartItem.dataset.stock);
            const newQty = currentQty + change;

            if (newQty <= 0) {
                  removeFromGuestCart(productId);
                  return;
            }

            // Check if new quantity exceeds stock
            if (newQty > stockQty) {
                  showNotification(`Cannot add more than ${stockQty} items. Only ${stockQty} available in stock.`, 'error');
                  return;
            }

            fetch('index.php', {
                        method: 'POST',
                        headers: {
                              'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=update_cart&product_id=${productId}&quantity=${newQty}`
                  })
                  .then(response => response.json())
                  .then(data => {
                        if (data.success) {
                              fetchGuestCart();
                              updateCartBadge();
                        }
                  });
      }

      function removeFromGuestCart(productId) {
            fetch('index.php', {
                        method: 'POST',
                        headers: {
                              'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=remove_from_cart&product_id=${productId}`
                  })
                  .then(response => response.json())
                  .then(data => {
                        if (data.success) {
                              fetchGuestCart();
                              updateCartBadge();
                        }
                  });
      }

      function clearGuestCart() {
            if (confirm('Are you sure you want to clear the cart?')) {
                  fetch('index.php', {
                              method: 'POST',
                              headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                              },
                              body: 'action=clear_guest_cart'
                        })
                        .then(response => response.json())
                        .then(data => {
                              if (data.success) {
                                    fetchGuestCart();
                                    updateCartBadge();
                                    showNotification('Cart cleared successfully', 'success');
                              }
                        });
            }
      }

      function fetchGuestCart() {
            fetch('index.php', {
                        method: 'POST',
                        headers: {
                              'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'action=fetch_guest_cart'
                  })
                  .then(res => res.json())
                  .then(data => {
                        if (data.success) {
                              document.getElementById('cartItemsContainer').innerHTML = data.html;
                              document.getElementById('cartTotal').textContent = data.total;

                              // Show/hide checkout and clear buttons
                              const checkoutBtn = document.getElementById('checkoutBtn');
                              const clearCartBtn = document.getElementById('clearCartBtn');

                              console.log('Cart data:', data); // Debug log

                              if (data.html.includes('Cart is empty') || data.total === '$0.00' || data.total === '$0') {
                                    if (checkoutBtn) {
                                          checkoutBtn.style.display = 'none';
                                          checkoutBtn.disabled = true;
                                    }
                                    if (clearCartBtn) {
                                          clearCartBtn.style.display = 'none';
                                    }
                              } else {
                                    if (checkoutBtn) {
                                          checkoutBtn.style.display = 'block';
                                          checkoutBtn.disabled = false;
                                          checkoutBtn.innerHTML = '<i class="fas fa-credit-card me-2"></i>Checkout';

                                          // Add a small delay to ensure button is fully ready
                                          setTimeout(() => {
                                                if (checkoutBtn) {
                                                      checkoutBtn.disabled = false;
                                                }
                                          }, 100);
                                    }
                                    if (clearCartBtn) {
                                          clearCartBtn.style.display = 'block';
                                    }
                              }
                        }
                  })
                  .catch(error => {
                        console.error('Error fetching cart:', error);
                  });
      }

      function updateCartBadge() {
            fetch('index.php', {
                        method: 'POST',
                        headers: {
                              'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'action=fetch_guest_cart'
                  })
                  .then(res => res.json())
                  .then(data => {
                        const badge = document.getElementById('cartBadge');
                        let itemCount = 0;

                        if (data.success && data.html && !data.html.includes('Cart is empty')) {
                              // Parse the cart HTML to count quantities
                              // But better: add a new field in your PHP response with the total quantity
                              if (data.total_quantity !== undefined) {
                                    itemCount = data.total_quantity;
                              }
                        }

                        if (itemCount > 0) {
                              badge.textContent = itemCount;
                              badge.style.display = 'flex';
                        } else {
                              badge.style.display = 'none';
                        }
                  });
      }

      function showPaymentModal() {
            console.log('showPaymentModal called'); // Debug log

            const total = document.getElementById('cartTotal').textContent;
            const checkoutBtn = document.getElementById('checkoutBtn');

            console.log('Cart total:', total); // Debug log
            console.log('Checkout button:', checkoutBtn); // Debug log

            // Check if checkout button exists
            if (!checkoutBtn) {
                  console.error('Checkout button not found');
                  showNotification('Error: Checkout button not found', 'error');
                  return;
            }

            // Check if cart total is null, empty, or zero
            if (!total || total === '$0.00' || total === '$0' || total.trim() === '') {
                  showNotification('Cart is empty. Please add items before checkout.', 'error');
                  return;
            }

            // Check if button is visible
            if (checkoutBtn.style.display === 'none') {
                  showNotification('Please wait while cart is loading...', 'error');
                  return;
            }

            // Check if button is already disabled
            if (checkoutBtn.disabled) {
                  console.log('Checkout button is disabled, skipping...');
                  return;
            }

            // Disable checkout button to prevent multiple clicks
            checkoutBtn.disabled = true;
            checkoutBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';

            try {
                  const usdAmount = parseFloat(total.replace('$', ''));
                  const modalTotalElement = document.getElementById('modal-total');

                  if (!modalTotalElement) {
                        throw new Error('modal-total element not found');
                  }

                  modalTotalElement.textContent = usdAmount.toFixed(2);

                  // Initialize currency display
                  updateCurrencyDisplay();

                  // Reset cash fields when opening modal
                  document.getElementById('guest_amount_tendered').value = '';
                  document.getElementById('guest_change_amount').value = '';
                  document.getElementById('guest-insufficient-amount').style.display = 'none';

                  // Show/hide cash fields based on payment method
                  toggleGuestCashFields();

                  const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
                  paymentModal.show();
                  console.log('Payment modal opened successfully'); // Debug log

                  // Add visual feedback for logged-in customers
                  const customerNameField = document.getElementById('customer_name');
                  const customerEmailField = document.getElementById('customer_email');

                  if (customerNameField && customerNameField.readOnly) {
                        customerNameField.style.backgroundColor = '#f8f9fa';
                        customerNameField.style.borderColor = '#28a745';
                  }

                  if (customerEmailField && customerEmailField.readOnly) {
                        customerEmailField.style.backgroundColor = '#f8f9fa';
                        customerEmailField.style.borderColor = '#28a745';
                  }

            } catch (error) {
                  console.error('Error showing payment modal:', error);
                  showNotification('Error opening payment modal', 'error');

                  // Re-enable button on error
                  checkoutBtn.disabled = false;
                  checkoutBtn.innerHTML = '<i class="fas fa-credit-card me-2"></i>Checkout';
                  return;
            }

            // Re-enable button after modal is shown
            setTimeout(() => {
                  if (checkoutBtn) {
                        checkoutBtn.disabled = false;
                        checkoutBtn.innerHTML = '<i class="fas fa-credit-card me-2"></i>Checkout';
                        console.log('Checkout button re-enabled'); // Debug log
                  }
            }, 1000);
      }

      function toggleGuestCashFields() {
            const paymentMethod = document.getElementById('payment_method').value;
            const cashFields = document.getElementById('guest-cash-fields');
            const quickAmounts = document.getElementById('guest-quick-amounts');
            const amountTendered = document.getElementById('guest_amount_tendered');
            const changeAmount = document.getElementById('guest_change_amount');
            const insufficientAmount = document.getElementById('guest-insufficient-amount');

            if (paymentMethod === 'cash') {
                  cashFields.style.display = 'block';
                  quickAmounts.style.display = 'block';
                  amountTendered.setAttribute('required', 'required');
                  changeAmount.setAttribute('readonly', 'readonly');
                  changeAmount.value = '';
                  insufficientAmount.style.display = 'none';
            } else {
                  cashFields.style.display = 'none';
                  quickAmounts.style.display = 'none';
                  amountTendered.removeAttribute('required');
                  changeAmount.setAttribute('readonly', 'readonly');
                  changeAmount.value = '';
                  insufficientAmount.style.display = 'none';
            }
      }

      function setGuestQuickAmount(amount) {
            document.getElementById('guest_amount_tendered').value = amount;
            calculateGuestChange();
      }

      function updateCurrencyDisplay() {
            const currencyCode = document.getElementById('currency_code').value;
            const modalTotalElement = document.getElementById('modal-total');

            if (!modalTotalElement) {
                  console.error('modal-total element not found');
                  return;
            }

            const usdAmount = parseFloat(modalTotalElement.textContent);

            if (!currencyCode || isNaN(usdAmount)) {
                  return;
            }

            // Convert amount to selected currency
            fetch('index.php', {
                        method: 'POST',
                        headers: {
                              'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `action=convert_currency&amount=${usdAmount}&from_currency=USD&to_currency=${currencyCode}`
                  })
                  .then(response => response.json())
                  .then(data => {
                        if (data.success) {
                              // Update total display
                              document.getElementById('modal-total-display').textContent = data.formatted_amount;

                              // Update exchange rate info
                              if (currencyCode !== 'USD') {
                                    document.getElementById('exchange-rate-info').style.display = 'block';
                                    document.getElementById('current-rate').textContent = data.rate.toFixed(6);
                              } else {
                                    document.getElementById('exchange-rate-info').style.display = 'none';
                              }

                              // Update currency symbols
                              const symbol = data.formatted_amount.replace(/[\d.,]/g, '').trim();
                              document.getElementById('tendered-currency-symbol').textContent = symbol;
                              document.getElementById('change-currency-symbol').textContent = symbol;

                              // Update quick amount buttons
                              updateQuickAmountButtons(currencyCode);
                        }
                  })
                  .catch(error => {
                        console.error('Error converting currency:', error);
                  });
      }

      function updateQuickAmountButtons(currencyCode) {
            const amounts = [5, 10, 20, 50, 100];

            amounts.forEach(amount => {
                  fetch('index.php', {
                              method: 'POST',
                              headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                              },
                              body: `action=convert_currency&amount=${amount}&from_currency=USD&to_currency=${currencyCode}`
                        })
                        .then(response => response.json())
                        .then(data => {
                              if (data.success) {
                                    document.getElementById(`quick-${amount}`).textContent = data.formatted_amount;
                              }
                        })
                        .catch(error => {
                              console.error('Error updating quick amount:', error);
                        });
            });
      }

      function calculateGuestChange() {
            const currencyCode = document.getElementById('currency_code').value;
            const modalTotalElement = document.getElementById('modal-total');

            if (!modalTotalElement) {
                  console.error('modal-total element not found');
                  return;
            }

            const usdAmount = parseFloat(modalTotalElement.textContent);
            const amountTendered = parseFloat(document.getElementById('guest_amount_tendered').value) || 0;
            const changeAmount = document.getElementById('guest_change_amount');
            const insufficientAmount = document.getElementById('guest-insufficient-amount');
            const completeBtn = document.getElementById('guest-complete-payment-btn');

            if (isNaN(usdAmount) || isNaN(amountTendered) || !currencyCode) {
                  changeAmount.value = '';
                  insufficientAmount.style.display = 'none';
                  completeBtn.disabled = false;
                  return;
            }

            // Convert USD amount to selected currency for comparison
            fetch('index.php', {
                        method: 'POST',
                        headers: {
                              'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `action=convert_currency&amount=${usdAmount}&from_currency=USD&to_currency=${currencyCode}`
                  })
                  .then(response => response.json())
                  .then(data => {
                        if (data.success) {
                              const convertedTotal = data.converted_amount;
                              const change = amountTendered - convertedTotal;

                              changeAmount.value = change >= 0 ? change.toFixed(2) : '';

                              if (change < 0) {
                                    insufficientAmount.style.display = 'block';
                                    completeBtn.disabled = true;
                              } else {
                                    insufficientAmount.style.display = 'none';
                                    completeBtn.disabled = false;
                              }
                        }
                  })
                  .catch(error => {
                        console.error('Error calculating change:', error);
                  });
      }

      function showNotification(message, type) {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'} position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            notification.innerHTML = `
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
              `;

            document.body.appendChild(notification);

            // Auto remove after 3 seconds
            setTimeout(() => {
                  if (notification.parentElement) {
                        notification.remove();
                  }
            }, 3000);
      }

      function printReceipt() {
            if (currentOrderId) {
                  window.open(`guest_receipt.php?order_id=${currentOrderId}`, '_blank');
            }
      }

      function showCustomerLogin() {
            // Store current cart data in sessionStorage
            const cartData = JSON.stringify({
                  total: document.getElementById('cartTotal').textContent,
                  items: document.getElementById('cartItemsContainer').innerHTML
            });
            sessionStorage.setItem('pendingCart', cartData);

            // Redirect to login page
            window.location.href = 'login.php?redirect=checkout';
      }

      function showCustomerRegister() {
            // Store current cart data in sessionStorage
            const cartData = JSON.stringify({
                  total: document.getElementById('cartTotal').textContent,
                  items: document.getElementById('cartItemsContainer').innerHTML
            });
            sessionStorage.setItem('pendingCart', cartData);

            // Redirect to customer registration page
            window.location.href = 'customer_register.php?redirect=checkout';
      }

      // Payment form submission
      document.getElementById('paymentForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('action', 'process_guest_payment');

            fetch('index.php', {
                        method: 'POST',
                        body: formData
                  })
                  .then(response => response.json())
                  .then(data => {
                        if (data.success) {
                              currentOrderId = data.order_id;
                              document.getElementById('order-id').textContent = data.order_id;
                              bootstrap.Modal.getInstance(document.getElementById('paymentModal')).hide();
                              new bootstrap.Modal(document.getElementById('successModal')).show();

                              // Update cart after successful payment
                              fetchGuestCart();
                              updateCartBadge();
                              closeCart();
                        } else {
                              showNotification(data.message, 'error');
                        }
                  })
                  .catch(error => {
                        showNotification('Error processing payment', 'error');
                  });
      });

      // Initialize cart badge on page load
      document.addEventListener('DOMContentLoaded', function() {
            updateCartBadge();

            // Check if we need to restore cart from sessionStorage
            if (window.location.search.includes('restore_cart=1')) {
                  const pendingCart = sessionStorage.getItem('pendingCart');
                  if (pendingCart) {
                        try {
                              const cartData = JSON.parse(pendingCart);
                              // Clear the stored cart data
                              sessionStorage.removeItem('pendingCart');

                              // Show notification
                              showNotification('Welcome back! Your cart has been restored.', 'success');

                              // Refresh cart display
                              fetchGuestCart();
                        } catch (e) {
                              console.error('Error restoring cart:', e);
                        }
                  }
            }

            // Show welcome message for newly registered customers
            if (window.location.search.includes('registered=1')) {
                  showNotification('Registration successful! Welcome to our store.', 'success');
                  // Clean up URL
                  window.history.replaceState({}, document.title, window.location.pathname);
            }

            // Show welcome message for logged in customers
            if (window.location.search.includes('logged_in=1')) {
                  showNotification('Welcome back! You are now logged in.', 'success');
                  // Clean up URL
                  window.history.replaceState({}, document.title, window.location.pathname);
            }
      });

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
                  } else {
                        entry.target.style.opacity = '0';
                        entry.target.style.transform = 'translateY(20px)';
                  }
            });
      }, observerOptions);

      document.querySelectorAll('.stat-number, .product-item-container, .powerful-features').forEach(stat => {
            stat.style.opacity = '0';
            stat.style.transform = 'translateY(20px)';
            stat.style.transition = 'all 0.6s ease';
            observer.observe(stat);
      });

      function updateGuestQuantityDirect(productId, newQuantity) {
            const cartItem = document.querySelector(`#cart-item-${productId}`);
            const quantity = parseInt(newQuantity);
            const stockQty = parseInt(cartItem.dataset.stock);

            if (isNaN(quantity) || quantity <= 0) {
                  // Reset to 1 if invalid input
                  cartItem.querySelector('.cart-qty-input').value = 1;
                  return;
            }

            // Check if quantity exceeds stock
            if (quantity > stockQty) {
                  showNotification(`Cannot add more than ${stockQty} items. Only ${stockQty} available in stock.`, 'error');
                  // Reset to stock quantity
                  cartItem.querySelector('.cart-qty-input').value = stockQty;
                  return;
            }

            fetch('index.php', {
                        method: 'POST',
                        headers: {
                              'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `action=update_cart&product_id=${productId}&quantity=${quantity}`
                  })
                  .then(response => response.json())
                  .then(data => {
                        if (data.success) {
                              fetchGuestCart();
                              updateCartBadge();
                        }
                  });
      }

      // Function removed - no longer needed since we use span instead of input

      // Product Detail Modal Functions
      let currentProductId = null;

      function showProductDetailModal(productId) {
            currentProductId = productId;

            // Fetch product details
            fetch('index.php', {
                        method: 'POST',
                        headers: {
                              'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `action=get_product_details&product_id=${productId}`
                  })
                  .then(response => response.json())
                  .then(data => {
                        if (data.success) {
                              populateProductModal(data.product);
                              const modal = new bootstrap.Modal(document.getElementById('productDetailModal'));
                              modal.show();
                        } else {
                              showNotification(data.message, 'error');
                        }
                  })
                  .catch(error => {
                        console.error('Error fetching product details:', error);
                        showNotification('Error loading product details', 'error');
                  });
      }

      function populateProductModal(product) {
            // Set product image
            document.getElementById('modal-product-image').src = product.image_path;
            document.getElementById('modal-product-image').alt = product.name;

            // Set product name and description
            document.getElementById('modal-product-name').textContent = product.name;
            document.getElementById('modal-product-description').textContent = product.description;

            // Set product info
            document.getElementById('modal-product-category').textContent = product.category;
            document.getElementById('modal-product-stock').textContent = product.stock_quantity;
            document.getElementById('modal-product-code').textContent = product.product_code || 'N/A';
            document.getElementById('modal-product-barcode').textContent = product.barcode || 'N/A';

            // Set prices
            const currentPriceElement = document.getElementById('modal-current-price');
            const originalPriceElement = document.getElementById('modal-original-price');
            const discountBadge = document.getElementById('modal-discount-badge');
            const discountPercent = document.getElementById('modal-discount-percent');

            currentPriceElement.textContent = `$${parseFloat(product.display_price).toFixed(2)}`;

            if (product.has_discount) {
                  originalPriceElement.textContent = `$${parseFloat(product.price).toFixed(2)}`;
                  originalPriceElement.style.display = 'block';
                  discountBadge.style.display = 'block';
                  discountPercent.textContent = `${product.discount_percent}%`;
            } else {
                  originalPriceElement.style.display = 'none';
                  discountBadge.style.display = 'none';
            }

            // Handle clothing-specific attributes
            const clothingAttributes = document.getElementById('modal-clothing-attributes');
            const sizeBadge = document.getElementById('modal-product-size');
            const colorBadge = document.getElementById('modal-product-color');
            const materialBadge = document.getElementById('modal-product-material');
            const weightBadge = document.getElementById('modal-product-weight');

            if (product.type === 'Clothes') {
                  clothingAttributes.style.display = 'block';

                  // Show size if available
                  if (product.size) {
                        sizeBadge.style.display = 'inline-block';
                        sizeBadge.querySelector('.size-value').textContent = product.size;
                  } else {
                        sizeBadge.style.display = 'none';
                  }

                  // Show color if available
                  if (product.color) {
                        colorBadge.style.display = 'inline-block';
                        colorBadge.querySelector('.color-value').textContent = product.color;
                  } else {
                        colorBadge.style.display = 'none';
                  }

                  // Show material if available
                  if (product.material) {
                        materialBadge.style.display = 'inline-block';
                        materialBadge.querySelector('.material-value').textContent = product.material;
                  } else {
                        materialBadge.style.display = 'none';
                  }

                  // Show weight if available
                  if (product.weight) {
                        weightBadge.style.display = 'inline-block';
                        weightBadge.querySelector('.weight-value').textContent = product.weight;
                  } else {
                        weightBadge.style.display = 'none';
                  }
            } else {
                  clothingAttributes.style.display = 'none';
            }

            // Reset quantity to 1
            document.getElementById('modal-quantity').value = 1;
      }

      function updateModalQuantity(change) {
            const quantityInput = document.getElementById('modal-quantity');
            let currentQuantity = parseInt(quantityInput.value);
            let newQuantity = currentQuantity + change;

            // Ensure quantity doesn't go below 1
            if (newQuantity < 1) {
                  newQuantity = 1;
            }

            quantityInput.value = newQuantity;
      }

      function addToCartFromModal() {
            if (!currentProductId) return;

            const quantity = parseInt(document.getElementById('modal-quantity').value);

            fetch('index.php', {
                        method: 'POST',
                        headers: {
                              'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `action=add_to_cart&product_id=${currentProductId}&quantity=${quantity}`
                  })
                  .then(response => response.json())
                  .then(data => {
                        if (data.success) {
                              showNotification(`${quantity} item(s) added to cart!`, 'success');
                              updateCartBadge();
                              fetchGuestCart();

                              // Close the modal
                              const modal = bootstrap.Modal.getInstance(document.getElementById('productDetailModal'));
                              modal.hide();
                        } else {
                              showNotification(data.message, 'error');
                        }
                  })
                  .catch(error => {
                        showNotification('Error adding product to cart', 'error');
                  });
      }

      // Add keyboard support for modal quantity
      document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                  // Close product detail modal when Escape is pressed
                  const modal = bootstrap.Modal.getInstance(document.getElementById('productDetailModal'));
                  if (modal) {
                        modal.hide();
                  }
            }
      });

      // Category filtering function
      function filterByCategory(category) {
            // Scroll to products section
            document.getElementById('products').scrollIntoView({
                  behavior: 'smooth'
            });

            // Update active button state
            updateActiveFilterButton(category);

            // Add a small delay to ensure the section is visible
            setTimeout(() => {
                  const productContainers = document.querySelectorAll('.product-item-container');

                  productContainers.forEach(container => {
                        const categoryBadges = container.querySelectorAll('.category-badge .badge');
                        let shouldShow = false;

                        if (category === 'All Products') {
                              shouldShow = true;
                        } else if (category === 'Clothes') {
                              // For Clothes filter, check if any badge contains "Clothes" (type badge)
                              categoryBadges.forEach(badge => {
                                    const badgeText = badge.textContent.trim();
                                    if (badgeText === 'Clothes') {
                                          shouldShow = true;
                                    }
                              });
                        } else if (category === 'Food') {
                              // For Food filter, check if any badge contains "Food" (type badge)
                              categoryBadges.forEach(badge => {
                                    const badgeText = badge.textContent.trim();
                                    if (badgeText === 'Food') {
                                          shouldShow = true;
                                    }
                              });
                        } else {
                              // For specific categories, check category badges
                              categoryBadges.forEach(badge => {
                                    const badgeText = badge.textContent.trim();
                                    if (badgeText === category) {
                                          shouldShow = true;
                                    }
                              });
                        }

                        container.style.display = shouldShow ? 'block' : 'none';
                  });

                  // Show notification
                  const categoryName = category === 'All Products' ? 'All Products' : category;
                  showNotification(`Showing ${categoryName}`, 'info');
            }, 500);
      }

      // Function to update active filter button
      function updateActiveFilterButton(activeCategory) {
            // Remove active class from all filter buttons
            const allButtons = document.querySelectorAll('.category-filter-buttons .btn');
            allButtons.forEach(btn => {
                  btn.classList.remove('active');
                  // Reset button styles
                  btn.classList.remove('btn-primary', 'btn-success', 'btn-info', 'btn-warning', 'btn-secondary', 'btn-danger', 'btn-dark');
                  btn.classList.add('btn-outline-primary', 'btn-outline-success', 'btn-outline-info', 'btn-outline-warning', 'btn-outline-secondary', 'btn-outline-danger', 'btn-outline-dark');
            });

            // Add active class to the clicked button
            const activeButton = document.querySelector(`.category-filter-buttons .btn[onclick*="${activeCategory}"]`);
            if (activeButton) {
                  activeButton.classList.add('active');
                  // Change button style to solid
                  const currentClasses = activeButton.className;
                  if (currentClasses.includes('btn-outline-primary')) {
                        activeButton.classList.remove('btn-outline-primary');
                        activeButton.classList.add('btn-primary');
                  } else if (currentClasses.includes('btn-outline-success')) {
                        activeButton.classList.remove('btn-outline-success');
                        activeButton.classList.add('btn-success');
                  } else if (currentClasses.includes('btn-outline-info')) {
                        activeButton.classList.remove('btn-outline-info');
                        activeButton.classList.add('btn-info');
                  } else if (currentClasses.includes('btn-outline-warning')) {
                        activeButton.classList.remove('btn-outline-warning');
                        activeButton.classList.add('btn-warning');
                  } else if (currentClasses.includes('btn-outline-secondary')) {
                        activeButton.classList.remove('btn-outline-secondary');
                        activeButton.classList.add('btn-secondary');
                  } else if (currentClasses.includes('btn-outline-danger')) {
                        activeButton.classList.remove('btn-outline-danger');
                        activeButton.classList.add('btn-danger');
                  } else if (currentClasses.includes('btn-outline-dark')) {
                        activeButton.classList.remove('btn-outline-dark');
                        activeButton.classList.add('btn-dark');
                  }
            }
      }
</script>