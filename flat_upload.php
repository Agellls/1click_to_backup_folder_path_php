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

if (empty($_SESSION['upload_token'])) {
    $authUrl = $client->createAuthUrl();
    header("Location:" . $authUrl);
} else {
?>
    <h1>Upload Folder to GDrive</h1>
    <form method="post" action="">
        Folder Path: <input type="text" name="folderPath" required><br>
        Zip File Name: <input type="text" name="zipName" required> .zip<br>
        <input type="submit" value="Zip and Upload" name="submit">
    </form>
<?php
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

            echo "Uploaded: " . $result->name . "<br>";

            // Clean up the temporary zip file
            unlink($zipPath);
        } else {
            echo "Failed to create zip file.<br>";
        }
    }
}

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    $_SESSION['upload_token'] = $token;
}
?>