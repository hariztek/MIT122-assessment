<?php
/**
 * includes/footer.php
 *
 * Closes out the markup opened by includes/header.php (the
 * .site-main / .container divs), then renders the site footer and
 * closes the HTML document. Always the last thing a page includes.
 */
?>
    </div>
</main>
<footer class="site-footer">
    <div class="container site-footer__inner">
        <p class="site-footer__brand">Student SkillBridge</p>
        <p class="site-footer__meta">A free peer-to-peer skill-learning platform for university and college students &middot; MIT122 Assessment 2</p>
    </div>
</footer>
<script src="/assets/js/main.js?v=<?= filemtime(__DIR__ . '/../assets/js/main.js') ?>" defer></script>
<script src="/assets/js/validation.js?v=<?= filemtime(__DIR__ . '/../assets/js/validation.js') ?>" defer></script>
</body>
</html>
