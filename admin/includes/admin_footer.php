                </div>
                <!-- /.container-fluid -->

                <footer class="border-top bg-white py-3 mt-auto">
                    <div class="container-fluid text-center text-muted">
                        <small>
                            © 2026 Smart Tiffin Delivery & Tracking System.
                            All rights reserved.
                        </small>
                    </div>
                </footer>

            </main>
            <!-- /.admin-content -->

        </div>
        <!-- /.admin-main -->

    </div>
    <!-- /.admin-wrapper -->


    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
    </script>

    <script src="../assets/js/main.js"></script>

    <script src="../assets/js/admin.js"></script>

    <?php if (isset($page_script) && !empty($page_script)): ?>
        <script src="<?php echo htmlspecialchars($page_script); ?>"></script>
    <?php endif; ?>

</body>
</html>