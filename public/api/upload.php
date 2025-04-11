<?php
/**
 * This api endpoint uploads files into the 'images/' directory. It is invoked from the 'editProject.php' page.
 */
include_once '../bootstrap.php';

use DataAccess\EquipmentDao;
use Model\EquipmentImage;
use Util\Security;

/**
 * Simple function that allows us to respond with a response code and a message inside a JSON object.
 *
 * @param int  $code the HTTP status code of the response
 * @param string $message the message to send back to the client
 * @return void
 */
function respond($code, $message) {
    header('Content-Type: application/json');
    header("X-PHP-Response-Code: $code", true, $code);
    echo '{"message": "' . $message . '"}';
    die();
}

if ($_POST['action'] == 'uploadImage') {
    header('Content-Type: application/json');

    $dao = new EquipmentDao($dbConn, $logger);

    $id = $_POST['id'];
    if (empty($id)) {
        respond(400, "Must include ID of equipment in file upload request");
    }

    if (isset($_FILES['image'])) {
        $file_name = $_FILES['image']['name'];
        $file_size = $_FILES['image']['size'];
        $file_tmp  = $_FILES['image']['tmp_name'];
	
        $supported_image = array(
            'gif',
            'jpg',
            'jpeg',
            'png'
        );
        $path_parts = pathinfo($file_name);
        $file_name = Security::HtmlEntitiesEncode($file_name);
        $extension = strtolower($path_parts['extension']);
       
        if(!in_array($extension, $supported_image))
        {
            respond(400, "File must be an image");
        
        }

        if ($file_size > (5 * 2097152)) {
            respond(400, "File size must be less than 10MB");
        }
	
        $equipment = $dao->getEquipment($id);
        // TODO: handle case when no project is found

		$image = new EquipmentImage();
		$imageId = $image->getEquipmentImageID();

        if (count($equipment->getEquipmentImages()) == 0) {
            $image->setEquipmentImageIsDefault(true);
        }
        $image->setEquipmentImageName($file_name);
        $image->setEquipment($equipment);

        $ok = move_uploaded_file($file_tmp, PUBLIC_FILES . '/images' . "/$imageId");

        if (!$ok) {
            respond(500, "Failed to upload the new image");
        }

        $ok = $dao->addNewEquipmentImage($image);
        if (!$ok) {
            $logger->warn("Image was uploaded with id '$imageId', but inserting metadata into the database failed");
            respond(500, "Failed to upload the new image");
        }

        respond(201, "Successfully uploaded a new image", $imageId);
    }
}
