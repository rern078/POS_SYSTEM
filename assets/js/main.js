// Top Header Scroll Animation
document.addEventListener('DOMContentLoaded', function () {
      const topHeader = document.querySelector('.top-header');
      let lastScrollTop = 0;
      let scrollThreshold = 100; // Minimum scroll distance before animation starts
      let isAnimating = false;

      if (topHeader) {
            window.addEventListener('scroll', function () {
                  const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                  const scrollDelta = scrollTop - lastScrollTop;

                  // Only animate if we've scrolled past the threshold
                  if (Math.abs(scrollDelta) > 5) { // Minimum scroll sensitivity
                        if (scrollTop > scrollThreshold) {
                              // Scrolling down - hide header
                              if (scrollDelta > 0 && !topHeader.classList.contains('scroll-down')) {
                                    topHeader.classList.remove('scroll-up');
                                    topHeader.classList.add('scroll-down');
                              }
                              // Scrolling up - show header
                              else if (scrollDelta < 0 && !topHeader.classList.contains('scroll-up')) {
                                    topHeader.classList.remove('scroll-down');
                                    topHeader.classList.add('scroll-up');
                              }
                        } else {
                              // Near top of page - always show header
                              topHeader.classList.remove('scroll-down', 'scroll-up');
                        }
                  }

                  lastScrollTop = scrollTop;
            });

            // Add smooth reveal animation when page loads
            setTimeout(() => {
                  topHeader.style.transition = 'transform 0.3s ease-in-out, opacity 0.3s ease-in-out';
            }, 100);
      }
});

// Optional: Add intersection observer for better performance
document.addEventListener('DOMContentLoaded', function () {
      const topHeader = document.querySelector('.top-header');

      if (topHeader && 'IntersectionObserver' in window) {
            const observer = new IntersectionObserver(
                  (entries) => {
                        entries.forEach(entry => {
                              if (entry.isIntersecting) {
                                    // Header is visible
                                    topHeader.classList.remove('scroll-down');
                                    topHeader.classList.add('scroll-up');
                              }
                        });
                  },
                  {
                        threshold: 0.1,
                        rootMargin: '-10px 0px 0px 0px'
                  }
            );

            // Observe the main content area
            const mainContent = document.querySelector('main') || document.querySelector('.hero-section') || document.body;
            observer.observe(mainContent);
      }
});
