<?php
	require_once 'vendor/autoload.php';

	use MicrosoftAzure\Storage\Blob\BlobRestProxy;
	use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
	use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
	use MicrosoftAzure\Storage\Blob\Models\CreateContainerOptions;
	use MicrosoftAzure\Storage\Blob\Models\PublicAccessType;

	// create blob client
	$connectionString = "DefaultEndpointsProtocol=https;AccountName=".getenv('ACCOUNT_NAME').";AccountKey=".getenv('ACCOUNT_KEY');
	$blobClient = BlobRestProxy::createBlobService($connectionString);
	$errors = [];
	$imageUrl = isset($_GET['image_url']) ? $_GET['image_url'] : null;

	if(isset($_FILES['image'])){
		$fileName = $_FILES['image']['name'];
		$newFileName = uniqid();
		$fileSize = $_FILES['image']['size'];
		$fileTmp = $_FILES['image']['tmp_name'];
		$fileType = $_FILES['image']['type'];
		$tmp = explode('.', $fileName);
		$fileExt = strtolower(end($tmp));

		$extensions= array("jpeg","jpg","png");
		if(in_array($fileExt, $extensions) === false){
			$errors[]="Extension yang diperbolehkan hanya .jpeg dan .jpg";
		}

		$maxSize = 2097152; //2 MB;
		if($fileSize > $maxSize){
			$errors[]='Ukuran file melebihi 2 MB';
		}

		if(empty($errors)){

			$containerName = "webapp-1-images";
			try {
				$blobName = "$newFileName.$fileExt";
				$content = file_get_contents($fileTmp);
				$blobClient->createBlockBlob($containerName, $blobName, $content);
				$imageUrl = $blobClient->getBlobUrl($containerName, $blobName);
				$encodedUrl = urlencode($imageUrl);
				header("Location: index.php?image_url=$encodedUrl");
			} catch(ServiceException $e){
				$code = $e->getCode();
				$error_message = $e->getMessage();
				echo "<script type='text/javascript'>alert('Error code : $code');</script>";
				echo "<script type='text/javascript'>window.location.href = 'index.php'</script>";
			}
			catch(InvalidArgumentTypeException $e){
				$code = $e->getCode();
				$error_message = $e->getMessage();
				echo "<script type='text/javascript'>alert('Error code : $code');</script>";
				echo "<script type='text/javascript'>window.location.href = 'index.php'</script>";
			}
		}
		else {
			$imageUrl = null;
			echo "<script>window.history.pushState('', '', window.location.pathname);</script>";
		}
	}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<link rel="stylesheet" href="./index.css">
	<title>Azure Computer Vision</title>
</head>
<body>
	<div class="container">
		<div>
			<h1>Analisis Gambar</h1>
			<h2>dengan Azure Computer Vision</h2>
			<form action="" class="upload-form" method="POST" enctype="multipart/form-data">
				<input type="file" id="image" name="image" accept="image/jpg,image/jpeg" required />
				<button type="submit">Analisis</button>
			</form>
			<?php if(count($errors) > 0): ?>
				<?php foreach($errors as $error): ?>
					<li class="error-message"><?php echo $error ?></li>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<div>
			<h2>Hasil Analisis</h2>
			<img
				src="<?php echo $imageUrl ? $imageUrl : './assets/images/placeholder.jpg' ?>"
				alt="Placeholder"
				class="uploaded-image">
			<h3 id="analysis-result">Tidak ada gambar yang dianalisis</h3>
		</div>
	</div>
</body>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
<script>

	var imageUrl = "<?php echo $imageUrl ? $imageUrl : '' ?>";

	window.onload = function(){
		if(imageUrl) processImage(imageUrl);
	}

	function processImage($imageUrl) {

		var subscriptionKey = "774b02573e7b4d71bd69e9a78daae154";

		var uriBase = "https://ardafirdausr-student.cognitiveservices.azure.com/vision/v2.0/analyze";

		var params = {
				"visualFeatures": "Categories,Description,Color",
				"details": "",
				"language": "en",
		};

		$.ajax({
			url: uriBase + "?" + $.param(params),
			beforeSend: function(xhrObj){
					xhrObj.setRequestHeader("Content-Type","application/json");
					xhrObj.setRequestHeader("Ocp-Apim-Subscription-Key", subscriptionKey);
					$("#analysis-result").html("Menganalisis ...");
			},
			type: "POST",
			data: '{"url": ' + '"' + $imageUrl + '"}',
		})
		.done(function(data) {
			console.log(data);
			var caption = data.description.captions.length > 0
				? data.description.captions[0].text
				: "Tidak dapat dikenali";
			$("#analysis-result").html("Result: \"" + caption + "\"");
		})
		.fail(function(jqXHR, textStatus, errorThrown) {
				var errorString = (errorThrown === "") ? "Error. " :
						errorThrown + " (" + jqXHR.status + "): ";
				errorString += (jqXHR.responseText === "") ? "" :
						jQuery.parseJSON(jqXHR.responseText).message;
				$("#analysis-result").html(errorString);
		});
	};
</script>
</html>
