// Admin Template JavaScript
console.log('Admin JS loaded!');

document.addEventListener('DOMContentLoaded', function () {
      console.log('DOM Content Loaded!');
      // Initialize tooltips
      var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
      var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
      });

      // Initialize popovers
      var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
      var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
      });

      // Auto-hide alerts after 5 seconds
      setTimeout(function () {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function (alert) {
                  var bsAlert = new bootstrap.Alert(alert);
                  bsAlert.close();
            });
      }, 5000);

      // Confirm delete actions
      document.querySelectorAll('.btn-delete').forEach(function (button) {
            button.addEventListener('click', function (e) {
                  if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                        e.preventDefault();
                  }
            });
      });

      // Form validation
      document.querySelectorAll('form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                  if (!form.checkValidity()) {
                        e.preventDefault();
                        e.stopPropagation();
                  }
                  form.classList.add('was-validated');
            });
      });

      // Search functionality for tables
      document.querySelectorAll('.table-search').forEach(function (input) {
            input.addEventListener('keyup', function () {
                  var searchTerm = this.value.toLowerCase();
                  var table = this.closest('.card').querySelector('table');
                  var rows = table.querySelectorAll('tbody tr');

                  rows.forEach(function (row) {
                        var text = row.textContent.toLowerCase();
                        if (text.includes(searchTerm)) {
                              row.style.display = '';
                        } else {
                              row.style.display = 'none';
                        }
                  });
            });
      });

      // Sortable tables
      document.querySelectorAll('.table th[data-sort]').forEach(function (header) {
            header.addEventListener('click', function () {
                  var table = this.closest('table');
                  var tbody = table.querySelector('tbody');
                  var rows = Array.from(tbody.querySelectorAll('tr'));
                  var column = this.cellIndex;
                  var sortType = this.dataset.sort;

                  // Remove existing sort indicators
                  table.querySelectorAll('th').forEach(th => {
                        th.classList.remove('sort-asc', 'sort-desc');
                  });

                  // Determine sort direction
                  var isAsc = !this.classList.contains('sort-asc');
                  this.classList.toggle('sort-asc', isAsc);
                  this.classList.toggle('sort-desc', !isAsc);

                  // Sort rows
                  rows.sort(function (a, b) {
                        var aVal = a.cells[column].textContent.trim();
                        var bVal = b.cells[column].textContent.trim();

                        if (sortType === 'number') {
                              aVal = parseFloat(aVal.replace(/[^\d.-]/g, '')) || 0;
                              bVal = parseFloat(bVal.replace(/[^\d.-]/g, '')) || 0;
                        } else if (sortType === 'date') {
                              aVal = new Date(aVal);
                              bVal = new Date(bVal);
                        }

                        if (aVal < bVal) return isAsc ? -1 : 1;
                        if (aVal > bVal) return isAsc ? 1 : -1;
                        return 0;
                  });

                  // Reorder rows
                  rows.forEach(row => tbody.appendChild(row));
            });
      });

      // AJAX functions
      window.adminAjax = {
            // Generic AJAX request
            request: function (url, method, data, callback) {
                  var xhr = new XMLHttpRequest();
                  xhr.open(method, url, true);
                  xhr.setRequestHeader('Content-Type', 'application/json');
                  xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                  xhr.onreadystatechange = function () {
                        if (xhr.readyState === 4) {
                              if (xhr.status === 200) {
                                    try {
                                          var response = JSON.parse(xhr.responseText);
                                          callback(null, response);
                                    } catch (e) {
                                          callback('Invalid JSON response', null);
                                    }
                              } else {
                                    callback('Request failed with status: ' + xhr.status, null);
                              }
                        }
                  };

                  xhr.onerror = function () {
                        callback('Network error', null);
                  };

                  if (data) {
                        xhr.send(JSON.stringify(data));
                  } else {
                        xhr.send();
                  }
            },

            // GET request
            get: function (url, callback) {
                  this.request(url, 'GET', null, callback);
            },

            // POST request
            post: function (url, data, callback) {
                  this.request(url, 'POST', data, callback);
            },

            // PUT request
            put: function (url, data, callback) {
                  this.request(url, 'PUT', data, callback);
            },

            // DELETE request
            delete: function (url, callback) {
                  this.request(url, 'DELETE', null, callback);
            }
      };

      // Notification system
      window.showNotification = function (message, type = 'info', duration = 5000) {
            var alertClass = 'alert-' + type;
            var iconClass = 'fas fa-info-circle';

            switch (type) {
                  case 'success':
                        iconClass = 'fas fa-check-circle';
                        break;
                  case 'warning':
                        iconClass = 'fas fa-exclamation-triangle';
                        break;
                  case 'danger':
                        iconClass = 'fas fa-times-circle';
                        break;
            }

            var notification = document.createElement('div');
            notification.className = 'alert ' + alertClass + ' alert-dismissible fade show position-fixed';
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            notification.innerHTML = `
            <i class="${iconClass} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

            document.body.appendChild(notification);

            // Auto remove after duration
            setTimeout(function () {
                  if (notification.parentNode) {
                        notification.remove();
                  }
            }, duration);
      };

      // Loading spinner
      window.showLoading = function (show = true) {
            var spinner = document.getElementById('loading-spinner');
            if (!spinner) {
                  spinner = document.createElement('div');
                  spinner.id = 'loading-spinner';
                  spinner.className = 'position-fixed w-100 h-100 d-flex justify-content-center align-items-center';
                  spinner.style.cssText = 'top: 0; left: 0; background: rgba(0,0,0,0.5); z-index: 9999;';
                  spinner.innerHTML = `
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            `;
                  document.body.appendChild(spinner);
            }

            spinner.style.display = show ? 'flex' : 'none';
      };

      // Chart utilities
      window.updateChart = function (chartId, newData, newLabels) {
            var canvas = document.getElementById(chartId);
            if (canvas) {
                  var ctx = canvas.getContext('2d');
                  var chart = Chart.getChart(canvas);

                  if (chart) {
                        chart.data.labels = newLabels;
                        chart.data.datasets[0].data = newData;
                        chart.update();
                  }
            }
      };

      // Export functions
      window.exportTable = function (tableId, filename) {
            var table = document.getElementById(tableId);
            if (!table) return;

            var csv = [];
            var rows = table.querySelectorAll('tr');

            for (var i = 0; i < rows.length; i++) {
                  var row = [], cols = rows[i].querySelectorAll('td, th');

                  for (var j = 0; j < cols.length; j++) {
                        var text = cols[j].innerText.replace(/"/g, '""');
                        row.push('"' + text + '"');
                  }

                  csv.push(row.join(','));
            }

            var csvContent = 'data:text/csv;charset=utf-8,' + csv.join('\n');
            var encodedUri = encodeURI(csvContent);
            var link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', filename + '.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
      };

      // Print function
      window.printPage = function () {
            window.print();
      };

      // Date picker initialization
      if (typeof flatpickr !== 'undefined') {
            flatpickr('.date-picker', {
                  dateFormat: 'Y-m-d',
                  allowInput: true
            });
      }

      // Select2 initialization
      if (typeof $ !== 'undefined' && $.fn.select2) {
            $('.select2').select2({
                  theme: 'bootstrap-5'
            });
      }
});

// Utility functions
window.formatCurrency = function (amount) {
      return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
      }).format(amount);
};

window.formatDate = function (date) {
      return new Intl.DateTimeFormat('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
      }).format(new Date(date));
};

window.formatNumber = function (number) {
      return new Intl.NumberFormat('en-US').format(number);
};

// Admin Layout JavaScript

document.addEventListener('DOMContentLoaded', function () {
      // Sidebar toggle functionality
      const sidebar = document.getElementById('adminSidebar');
      const sidebarToggle = document.getElementById('sidebarToggle');
      const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
      const adminMain = document.querySelector('.admin-main');

      // Debug logging
      console.log('Sidebar elements found:', {
            sidebar: sidebar,
            sidebarToggle: sidebarToggle,
            sidebarToggleBtn: sidebarToggleBtn,
            adminMain: adminMain
      });

      // Check for saved sidebar state
      const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';

      if (sidebarCollapsed) {
            sidebar.classList.add('collapsed');
            adminMain.classList.add('sidebar-collapsed');
      }

      // Sidebar toggle handlers
      function toggleSidebar() {
            console.log('Toggle sidebar clicked!');
            console.log('Window width:', window.innerWidth);

            if (window.innerWidth > 992) {
                  // Desktop: toggle collapsed state
                  console.log('Desktop mode - toggling collapsed state');
                  sidebar.classList.toggle('collapsed');
                  adminMain.classList.toggle('sidebar-collapsed');
                  // Save state to localStorage
                  localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
                  console.log('Sidebar collapsed:', sidebar.classList.contains('collapsed'));
            } else {
                  // Mobile: toggle show/hide
                  console.log('Mobile mode - toggling show state');
                  sidebar.classList.toggle('show');
                  console.log('Sidebar shown:', sidebar.classList.contains('show'));
            }
      }

      if (sidebarToggle) {
            console.log('Adding click listener to sidebarToggle');
            sidebarToggle.addEventListener('click', function (e) {
                  console.log('Sidebar toggle clicked!');
                  e.preventDefault();
                  toggleSidebar();
            });
      } else {
            console.log('sidebarToggle element not found!');
      }

      if (sidebarToggleBtn) {
            console.log('Adding click listener to sidebarToggleBtn');
            sidebarToggleBtn.addEventListener('click', function (e) {
                  console.log('Sidebar toggle btn clicked!');
                  e.preventDefault();
                  toggleSidebar();
            });
      } else {
            console.log('sidebarToggleBtn element not found!');
      }

      // Close mobile sidebar when clicking outside
      document.addEventListener('click', function (e) {
            if (window.innerWidth <= 992 && sidebar && sidebarToggleBtn) {
                  if (!sidebar.contains(e.target) && !sidebarToggleBtn.contains(e.target)) {
                        sidebar.classList.remove('show');
                  }
            }
      });

      // Handle window resize
      window.addEventListener('resize', function () {
            if (window.innerWidth > 992) {
                  sidebar.classList.remove('show');
            }
      });

      // Auto-hide alerts after 5 seconds
      const alerts = document.querySelectorAll('.alert-modern');
      alerts.forEach(function (alert) {
            setTimeout(function () {
                  if (alert.parentNode) {
                        alert.style.transition = 'opacity 0.5s ease';
                        alert.style.opacity = '0';
                        setTimeout(function () {
                              if (alert.parentNode) {
                                    alert.parentNode.removeChild(alert);
                              }
                        }, 500);
                  }
            }, 5000);
      });

      // Table row selection
      const tableRows = document.querySelectorAll('.table-modern tbody tr');
      tableRows.forEach(function (row) {
            row.addEventListener('click', function () {
                  // Remove active class from all rows
                  tableRows.forEach(r => r.classList.remove('table-active'));
                  // Add active class to clicked row
                  this.classList.add('table-active');
            });
      });

      // Form validation enhancement
      const forms = document.querySelectorAll('.form-modern');
      forms.forEach(function (form) {
            const inputs = form.querySelectorAll('input, select, textarea');

            inputs.forEach(function (input) {
                  input.addEventListener('blur', function () {
                        validateField(this);
                  });

                  input.addEventListener('input', function () {
                        if (this.classList.contains('is-invalid')) {
                              validateField(this);
                        }
                  });
            });
      });

      function validateField(field) {
            const value = field.value.trim();
            const fieldName = field.name;
            let isValid = true;
            let errorMessage = '';

            // Remove existing validation classes
            field.classList.remove('is-valid', 'is-invalid');

            // Remove existing error message
            const existingError = field.parentNode.querySelector('.invalid-feedback');
            if (existingError) {
                  existingError.remove();
            }

            // Validation rules
            if (field.hasAttribute('required') && !value) {
                  isValid = false;
                  errorMessage = 'This field is required.';
            } else if (field.type === 'email' && value && !isValidEmail(value)) {
                  isValid = false;
                  errorMessage = 'Please enter a valid email address.';
            } else if (field.type === 'number' && value && isNaN(value)) {
                  isValid = false;
                  errorMessage = 'Please enter a valid number.';
            } else if (field.hasAttribute('minlength') && value.length < field.getAttribute('minlength')) {
                  isValid = false;
                  errorMessage = `Minimum length is ${field.getAttribute('minlength')} characters.`;
            } else if (field.hasAttribute('maxlength') && value.length > field.getAttribute('maxlength')) {
                  isValid = false;
                  errorMessage = `Maximum length is ${field.getAttribute('maxlength')} characters.`;
            }

            // Apply validation result
            if (isValid && value) {
                  field.classList.add('is-valid');
            } else if (!isValid) {
                  field.classList.add('is-invalid');

                  // Add error message
                  const errorDiv = document.createElement('div');
                  errorDiv.className = 'invalid-feedback';
                  errorDiv.textContent = errorMessage;
                  field.parentNode.appendChild(errorDiv);
            }
      }

      function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
      }

      // Loading states for buttons
      const buttons = document.querySelectorAll('.btn-modern');
      buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                  if (!this.classList.contains('btn-loading')) {
                        this.classList.add('btn-loading');
                        this.disabled = true;

                        // Add loading spinner
                        const originalText = this.innerHTML;
                        this.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Loading...';

                        // Remove loading state after 2 seconds (for demo purposes)
                        // In real applications, this should be removed when the action completes
                        setTimeout(() => {
                              this.classList.remove('btn-loading');
                              this.disabled = false;
                              this.innerHTML = originalText;
                        }, 2000);
                  }
            });
      });

      // Search functionality for tables
      const searchInputs = document.querySelectorAll('.table-search');
      searchInputs.forEach(function (searchInput) {
            searchInput.addEventListener('input', function () {
                  const searchTerm = this.value.toLowerCase();
                  const tableId = this.getAttribute('data-table');
                  const table = document.getElementById(tableId);

                  if (table) {
                        const rows = table.querySelectorAll('tbody tr');

                        rows.forEach(function (row) {
                              const text = row.textContent.toLowerCase();
                              if (text.includes(searchTerm)) {
                                    row.style.display = '';
                              } else {
                                    row.style.display = 'none';
                              }
                        });
                  }
            });
      });

      // Chart initialization (if Chart.js is available)
      if (typeof Chart !== 'undefined') {
            // Initialize any charts on the page
            const chartCanvases = document.querySelectorAll('canvas[data-chart]');
            chartCanvases.forEach(function (canvas) {
                  const chartType = canvas.getAttribute('data-chart');
                  const chartData = JSON.parse(canvas.getAttribute('data-chart-data') || '{}');

                  new Chart(canvas, {
                        type: chartType,
                        data: chartData,
                        options: {
                              responsive: true,
                              maintainAspectRatio: false,
                              plugins: {
                                    legend: {
                                          position: 'bottom',
                                    }
                              }
                        }
                  });
            });
      }

      // Notification system
      function showNotification(message, type = 'info', duration = 5000) {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-modern notification-toast`;
            notification.style.cssText = `
                  position: fixed;
                  top: 20px;
                  right: 20px;
                  z-index: 9999;
                  min-width: 300px;
                  animation: slideInRight 0.3s ease;
            `;
            notification.innerHTML = `
                  <div class="d-flex align-items-center">
                        <i class="fas fa-${getNotificationIcon(type)} me-2"></i>
                        <span>${message}</span>
                        <button type="button" class="btn-close ms-auto" onclick="this.parentElement.parentElement.remove()"></button>
                  </div>
            `;

            document.body.appendChild(notification);

            // Auto remove after duration
            setTimeout(() => {
                  if (notification.parentNode) {
                        notification.style.animation = 'slideOutRight 0.3s ease';
                        setTimeout(() => {
                              if (notification.parentNode) {
                                    notification.parentNode.removeChild(notification);
                              }
                        }, 300);
                  }
            }, duration);
      }

      function getNotificationIcon(type) {
            const icons = {
                  'success': 'check-circle',
                  'danger': 'exclamation-circle',
                  'warning': 'exclamation-triangle',
                  'info': 'info-circle'
            };
            return icons[type] || 'info-circle';
      }

      // Make notification function globally available
      window.showNotification = showNotification;

      // Add CSS animations for notifications
      const style = document.createElement('style');
      style.textContent = `
            @keyframes slideInRight {
                  from {
                        transform: translateX(100%);
                        opacity: 0;
                  }
                  to {
                        transform: translateX(0);
                        opacity: 1;
                  }
            }
            
            @keyframes slideOutRight {
                  from {
                        transform: translateX(0);
                        opacity: 1;
                  }
                  to {
                        transform: translateX(100%);
                        opacity: 0;
                  }
            }
            
            .table-active {
                  background-color: rgba(78, 115, 223, 0.1) !important;
            }
            
            .btn-loading {
                  pointer-events: none;
            }
      `;
      document.head.appendChild(style);
}); 