// auth.js - helpers for auth pages
window.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.show-password').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var target = this.getAttribute('data-target');
            var input = document.getElementById(target);
            if (!input) return;
            if (input.type === 'password') {
                input.type = 'text';
                this.textContent = 'HIDE';
            } else {
                input.type = 'password';
                this.textContent = 'SHOW';
            }
        });
    });

    var slides = Array.prototype.slice.call(document.querySelectorAll('#authSlideshow .auth-slide'));
    var dots = Array.prototype.slice.call(document.querySelectorAll('#authSlideDots .slide-dot'));
    var currentIndex = 0;
    var timer = null;

    function showSlide(index) {
        if (!slides.length) return;
        currentIndex = (index + slides.length) % slides.length;
        slides.forEach(function(slide, slideIndex) {
            slide.classList.toggle('is-active', slideIndex === currentIndex);
        });
        dots.forEach(function(dot, dotIndex) {
            dot.classList.toggle('is-active', dotIndex === currentIndex);
        });
    }

    function startSlideshow() {
        if (slides.length < 2) return;
        timer = window.setInterval(function() {
            showSlide(currentIndex + 1);
        }, 4500);
    }

    function resetSlideshow(index) {
        if (timer) {
            window.clearInterval(timer);
        }
        showSlide(index);
        startSlideshow();
    }

    dots.forEach(function(dot, index) {
        dot.addEventListener('click', function() {
            resetSlideshow(index);
        });
    });

    if (slides.length) {
        showSlide(0);
        startSlideshow();
    }
});
