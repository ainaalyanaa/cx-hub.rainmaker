<!DOCTYPE html>
<html lang="en" class="light scroll-smooth " dir="ltr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta content="HelpDesk - Online Ticket Support" name="description" />
    <meta name="website" content="https://w3bd.com" />
    <meta name="email" content="info@w3bd.com" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <link rel="icon" type="image/png" href="<?php echo e(setting('main_favicon', '/favicon.png')); ?>">
    <link rel="shortcut" href="<?php echo e(setting('main_favicon', '/favicon.png')); ?>">
    <link href="<?php echo e(asset('css/app.css')); ?>" rel="stylesheet">
    <link href="<?php echo e(asset('css/custom.css')); ?>" rel="stylesheet">
    <?php echo app('Tighten\Ziggy\BladeRouteGenerator')->generate(); ?>
    <?php echo app('Illuminate\Foundation\Vite')(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"]); ?>
    <?php if (!isset($__inertiaSsrDispatched)) { $__inertiaSsrDispatched = true; $__inertiaSsrResponse = app(\Inertia\Ssr\Gateway::class)->dispatch($page); }  if ($__inertiaSsrResponse) { echo $__inertiaSsrResponse->head; } ?>
    
    <!-- Fallback for installer if assets fail to load -->
    <script>
        // Check if Vue app loaded successfully
        window.addEventListener('load', function() {
            setTimeout(function() {
                if (!document.querySelector('#app') || document.querySelector('#app').innerHTML.trim() === '') {
                    console.error('Installer failed to load - showing fallback');
                    document.body.innerHTML = `
                        <div style="padding: 20px; font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto;">
                            <h1>HelpDesk Installation</h1>
                            <p>If you're seeing this message, the installer interface failed to load. This usually happens when:</p>
                            <ul>
                                <li>Assets are not compiled (run: npm run build)</li>
                                <li>JavaScript is disabled in your browser</li>
                                <li>There's a server configuration issue</li>
                            </ul>
                            <p><strong>Solution:</strong> Please run <code>npm run build</code> in your project directory and refresh this page.</p>
                            <p>If the problem persists, check your server error logs for more details.</p>
                        </div>
                    `;
                }
            }, 3000);
        });
    </script>
</head>
<body class="font-inter leading-none antialiased">
    <?php if (!isset($__inertiaSsrDispatched)) { $__inertiaSsrDispatched = true; $__inertiaSsrResponse = app(\Inertia\Ssr\Gateway::class)->dispatch($page); }  if ($__inertiaSsrResponse) { echo $__inertiaSsrResponse->body; } elseif (config('inertia.use_script_element_for_initial_page')) { ?><script data-page="app" type="application/json"><?php echo json_encode($page); ?></script><div id="app"></div><?php } else { ?><div id="app" data-page="<?php echo e(json_encode($page)); ?>"></div><?php } ?>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\rainMaker\resources\views/app.blade.php ENDPATH**/ ?>