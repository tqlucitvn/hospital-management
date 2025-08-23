<?php
require_once 'includes/config.php';
requireRole('ADMIN');

$pageTitle = 'Layout Test';
$user = getCurrentUser();

ob_start();
?>

<div class="container-fluid">
    <h1>🧪 Layout Test Page</h1>
    
    <div class="alert alert-info">
        <h4>Testing Layout Structure</h4>
        <p>This page tests if the layout is working correctly.</p>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h5>Test Card</h5>
        </div>
        <div class="card-body">
            <p>If you can see this card properly positioned to the right of the sidebar (not underneath it), then the layout is working correctly.</p>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="alert alert-success">
                        <strong>Sidebar Position:</strong> Should be fixed on the left
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="alert alert-warning">
                        <strong>Content Position:</strong> Should be on the right with 250px margin
                    </div>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Test Column 1</th>
                            <th>Test Column 2</th>
                            <th>Test Column 3</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Test Data 1</td>
                            <td>Test Data 2</td>
                            <td>Test Data 3</td>
                        </tr>
                        <tr>
                            <td>More Test Data</td>
                            <td>More Test Data</td>
                            <td>More Test Data</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div style="margin-top: 50px; padding: 20px; background: #e9ecef; border-radius: 8px;">
        <h5>CSS Debug Info:</h5>
        <ul>
            <li><strong>Body:</strong> Should have padding-top: 76px</li>
            <li><strong>Navbar:</strong> Should be fixed at top with height: 76px</li>
            <li><strong>Sidebar:</strong> Should be fixed position, width: 250px, top: 76px</li>
            <li><strong>Main Content:</strong> Should have margin-left: 250px</li>
        </ul>
    </div>
</div>

<style>
/* Debug styles to highlight layout */
.main-content {
    border: 2px dashed red !important;
    background: rgba(255, 0, 0, 0.05) !important;
}

.sidebar {
    border: 2px dashed blue !important;
    background: rgba(0, 0, 255, 0.05) !important;
}

.navbar {
    border: 2px dashed green !important;
}
</style>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?>
