// Admin Template JavaScript

document.addEventListener('DOMContentLoaded', function () {
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