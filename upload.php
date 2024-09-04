<?php
session_start();
include 'vendor/autoload.php';

// Function to zip a folder
function zipFolder($source, $destination)
{
    if (!extension_loaded('zip') || !file_exists($source)) {
        return false;
    }

    $zip = new ZipArchive();
    if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
        return false;
    }

    $source = str_replace('\\', '/', realpath($source));

    if (is_dir($source) === true) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);

        foreach ($files as $file) {
            $file = str_replace('\\', '/', $file);

            // Ignore "." and ".." folders
            if (in_array(substr($file, strrpos($file, '/') + 1), array('.', '..')))
                continue;

            $file = realpath($file);

            if (is_dir($file) === true) {
                $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
            } else if (is_file($file) === true) {
                $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
            }
        }
    } else if (is_file($source) === true) {
        $zip->addFromString(basename($source), file_get_contents($source));
    }

    return $zip->close();
}

// Google Drive API setup
$client = new Google_Client();
$client->setAuthConfig("oauth-credentials.json");
$client->addScope("https://www.googleapis.com/auth/drive");
$service = new Google_Service_Drive($client);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Folder to Google Drive</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Upload Folder to Google Drive</h3>
                    </div>
                    <div class="card-body">
                        <?php
                        if (empty($_SESSION['upload_token'])) {
                            $authUrl = $client->createAuthUrl();
                            echo '<div class="alert alert-info">Please <a href="' . $authUrl . '" class="alert-link">login with Google</a> to continue.</div>';
                        } else {
                        ?>
                            <form method="post" action="">
                                <div class="mb-3">
                                    <label for="folderPath" class="form-label">Folder Path:</label>
                                    <input type="text" class="form-control" id="folderPath" name="folderPath" required>
                                </div>
                                <div class="mb-3">
                                    <label for="zipName" class="form-label">Zip File Name:</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="zipName" name="zipName" required>
                                        <span class="input-group-text">.zip</span>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary" name="submit">Zip and Upload</button>
                            </form>
                        <?php
                        }

                        if (isset($_POST['submit'])) {
                            $folderPath = $_POST['folderPath'];
                            $zipName = $_POST['zipName'];
                            $zipFilename = $zipName . '.zip';
                            $zipPath = sys_get_temp_dir() . '/' . $zipFilename;

                            if (zipFolder($folderPath, $zipPath)) {
                                $client->setAccessToken($_SESSION['upload_token']);
                                $client->getAccessToken();

                                $file = new Google_Service_Drive_DriveFile();
                                $file->setName($zipFilename);

                                $result = $service->files->create($file, array(
                                    'data' => file_get_contents($zipPath),
                                    'mimeType' => 'application/zip',
                                    'uploadType' => 'multipart'
                                ));

                                echo '<div class="alert alert-success mt-3">Uploaded: ' . $result->name . '</div>';

                                // Clean up the temporary zip file
                                unlink($zipPath);
                            } else {
                                echo '<div class="alert alert-danger mt-3">Failed to create zip file.</div>';
                            }
                        }

                        if (isset($_GET['code'])) {
                            $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
                            $_SESSION['upload_token'] = $token;
                            // Redirect to remove the 'code' from the URL
                            header('Location: ' . $_SERVER['PHP_SELF']);
                            exit;
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS (optional, for certain Bootstrap features) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>