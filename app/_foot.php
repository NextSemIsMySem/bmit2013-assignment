    </main>

    <footer>
         <b>Fitness & Gym Equipment Online Store </b> &middot;
        Copyrighted &copy; <?= date('Y') ?>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <?php if (!empty($_photoEditor)): ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
    <?php endif; ?>
    <script src="/js/app.js?v=<?= filemtime(__DIR__ . '/js/app.js') ?>"></script>

</body>
</html>
