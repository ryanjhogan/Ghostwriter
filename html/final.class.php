<?php 

class final_rest
{

  /**
   * @api POST /final.php/callAI
   * @apiName callAI
   * @apiDescription Call OpenAI API to generate eBook content
   *
   * @apiParam {String} topic User's topic for the eBook
   *
   * @apiSuccess {Integer} status Status code (0 = success, 1 = error)
   * @apiSuccess {String} message Status message
   * @apiSuccess {String} response Generated eBook HTML content
   * @apiSuccess {Integer} tokens Total tokens used in API call
   *
   * @apiError {Integer} status Error status code (1)
   * @apiError {String} message Error description
   */
  public static function callAI() {
    try {
      $adminPrompt = "Your job is to build a 5 page eBook using the following users input as a guide. Use HTML to format it using this guide: Title should be a <h1>, chapters <h3> and paragraphs as <p>, add breaks after tilte and end of chapters. No index page needed, don't add metadata. It must be in the HTML format.";

      $apiKey = getenv('APIKEY');
      if (empty($apiKey)) {
        throw new Exception('OpenAI API key not set in environment (APIKEY)');
      }

      $userTopic = isset($_POST['topic']) ? $_POST['topic'] : '';

      // Use direct cURL request to OpenAI API
      $payload = [
        'model' => 'gpt-4o',
        'messages' => [
          ['role' => 'system', 'content' => $adminPrompt],
          ['role' => 'user', 'content' => $userTopic],
        ],
      ];

      $ch = curl_init('https://api.openai.com/v1/chat/completions');
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      // Timeouts to avoid hanging requests
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
      curl_setopt($ch, CURLOPT_TIMEOUT, 30);
      curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
      ]);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

      $raw = curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      // curl_close is a no-op in PHP 8.5+, avoid calling to prevent deprecation warnings

      if ($raw === false) {
        $err = curl_error($ch);
        throw new Exception('cURL error: ' . $err);
      }

      if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception('OpenAI API error (HTTP ' . $httpCode . '): ' . $raw);
      }

      $result = json_decode($raw, true);
      $responseContent = $result['choices'][0]['message']['content'] ?? null;
      $tokens = $result['usage']['total_tokens'] ?? null;

      if ($responseContent === null) {
        throw new Exception('OpenAI response missing content');
      }

      $retData = [];
      $retData['status'] = 0;
      $retData['message'] = 'AI call successful';
      $retData['response'] = $responseContent;
      $retData['tokens'] = $tokens;

      EXEC_SQL("insert into log (function, logdata) values (?,?)", "callAI", "{status : 0}");
    } catch (Throwable $e) {
      $retData = [];
      $retData['status'] = 1;
      $retData['message'] = $e->getMessage();
      EXEC_SQL("insert into log (function, logdata) values (?,?)", "callAI", $e->getMessage());
    }
    // Ensure JSON content type for responses
    header('Content-Type: application/json');
    return json_encode($retData);
  }

  /**
   * @api POST /final.php/logCost
   * @apiName logCost
   * @apiDescription Log API call cost and tokens to database
   *
   * @apiParam {Integer} tokens Number of tokens used
   * @apiParam {String} prompt User's original prompt
   *
   * @apiSuccess {Integer} status Status code (0 = success)
   * @apiSuccess {String} message Success message
   * @apiSuccess {Integer} promptid Generated prompt ID
   *
   * @apiError {Integer} status Error status code (1)
   * @apiError {String} message Error description
   */
  public static function logCost() {
    try {
      EXEC_SQL("insert into cost (tokens, prompt) values (?,?)", $_POST["tokens"], $_POST["prompt"] );
      $row = GET_SQL("select MAX(promptid) as promptid from cost");
      $retData["promptid"] = $row[0]["promptid"];
      $retData["status"] = 0;
      $retData["message"] = "cost log successful";
      EXEC_SQL("insert into log (function, logdata) values (?,?)", "logCost", "{status : 0}");
    } catch (Throwable $e) {
      $retData["status"] = 1;
      $retData["message"] = $e->getMessage();
      EXEC_SQL("insert into log (function, logdata) values (?,?)", "logCost", $e->getMessage());
    }
    header('Content-Type: application/json');
    return json_encode($retData);
  }



  /**
   * @api POST /final.php/buildBook
   * @apiName buildBook
   * @apiDescription Create and download an EPUB eBook file
   *
   * @apiParam {String} title eBook title
   * @apiParam {String} chapters Comma-separated chapter titles
   * @apiParam {String} author Author name
   * @apiParam {String} body HTML body content
   * @apiParam {Integer} promptid ID of the prompt that generated content
   * @apiParam {File} coverPhoto Cover image file (JPG/PNG)
   *
   * @apiSuccess {String} EPUB File EPUB file download
   *
   * @apiError {String} message Error message
   */
  public static function buildBook() {
    try {
    $title = $_POST["title"];
    $chapters = explode(",", $_POST["chapters"]);
    $author = $_POST["author"];
    $coverPhoto = $_FILES["coverPhoto"]["tmp_name"];
    $body = $_POST["body"];

    // Define EPUB folder structure
    $buildDir = "buildEPUB/";
    $contentDir = $buildDir . "EPUB/";
    $metaDir = $buildDir . "META-INF/";
    $imageDir = $contentDir . "images/";

    // Ensure directories exist
    if (!is_dir($buildDir)) mkdir($buildDir, 0777, true);
    if (!is_dir($contentDir)) mkdir($contentDir, 0777, true);
    if (!is_dir($imageDir)) mkdir($imageDir, 0777, true);

    // Create mimetype file (must be first, **no compression**)
    file_put_contents($buildDir . "mimetype", "application/epub+zip");

    

    // Handle Cover Image
    $coverFilename = "cover.jpg";
    $coverDestination = $imageDir . $coverFilename;
    
    if (!move_uploaded_file($coverPhoto, $coverDestination)) {
        die(json_encode(["status" => "error", "message" => "Error: Unable to upload cover image."]));
    }

    //AREA USED FOR FORMATTING BOOK

    file_put_contents($contentDir . "cover.xhtml", 
"<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<!DOCTYPE html>
<html xmlns=\"http://www.w3.org/1999/xhtml\" xmlns:epub=\"http://www.idpf.org/2007/ops\" lang=\"en\" xml:lang=\"en\">
	<head>
		<title>".$title."</title>
		<link rel=\"stylesheet\" type=\"text/css\" href=\"css/epub.css\" />
	</head>
    <h1>".$title."</h1>
    <h3>".$author."</h3>
	<body>
		<img src=\"images/".$coverFilename."\" alt=\"Cover Image\" title=\"Cover Image\" />
	</body>
</html>
    ");


    //Create container.xml
    file_put_contents($metaDir . "container.xml", 
"<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<container version=\"1.0\" xmlns=\"urn:oasis:names:tc:opendocument:xmlns:container\">
  <rootfiles>
    <rootfile full-path=\"EPUB/package.opf\" media-type=\"application/oebps-package+xml\"/>
  </rootfiles>
</container>
    ");

    //Sets chapter hyperlinks in index page
    $tableOfContents = "";
    $index = 1;
    foreach ($chapters as $chapter) {
        $tableOfContents = $tableOfContents . 
                        "<br></br>
                        <li id=\"np-315\" class=\"front\">
							<a href=\"content.xhtml#".$index."\">".$chapter."</a>
			            </li>";
        $index++;
    }

    //Assigns hyperlink IDs to body page
    $index = 1;
    $body = preg_replace_callback('/<h3>/', function() use (&$index) {
    return '<h3 id="' . $index++ . '">';
    }, $body); 
    $body =  str_replace("<br>","<br></br>",$body);
    // meta charset being put in body tag, this removes that
    $body = str_replace("<meta charset=\"UTF-8\">", "", $body);

    //Create nav.xhtml
    file_put_contents($contentDir . "nav.xhtml", 
"<?xml version=\"1.0\" encoding=\"utf-8\"?>
<!DOCTYPE html>
<html xmlns=\"http://www.w3.org/1999/xhtml\" xmlns:epub=\"http://www.idpf.org/2007/ops\" xml:lang=\"en\"
	lang=\"en\">
	<head>
		<title>".$title."</title>
		<link href=\"css/epub.css\" rel=\"stylesheet\" type=\"text/css\"/>
		<link href=\"css/nav.css\" rel=\"stylesheet\" type=\"text/css\"/>
	</head>
	<body>
		<nav epub:type=\"toc\" id=\"toc\">
			<h2>Table of Contents</h2>
			<ol id=\"tocList\">
				<li id=\"np-313\">
					<a href=\"content.xhtml#0\">".$title."</a>
					<ol>
						".$tableOfContents."
					</ol>
				</li>
			</ol>
		</nav>
	</body>
</html>
    ");

    // Create package.opf (metadata & manifest)
    file_put_contents($contentDir . "package.opf", 
    "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
    <package xmlns=\"http://www.idpf.org/2007/opf\" version=\"2.0\" unique-identifier=\"urn:uuid:12345678-1234-1234-1234-123456789abc\">
        <metadata xmlns:dc=\"http://purl.org/dc/elements/1.1/\">
        <dc:identifier id=\"BookId\">urn:uuid:12345678-1234-1234-1234-123456789abc</dc:identifier>
            <dc:title>". $title . "</dc:title>
            <dc:creator>" . $author . "</dc:creator>
            <dc:language>en</dc:language>
            <meta name=\"cover\" content=\"cover-image\"/>
            <dc:date>".date('Y-m-d')."</dc:date>
        </metadata>
        <manifest>
            <item id=\"ncx\" href=\"toc.ncx\" media-type=\"application/x-dtbncx+xml\"/>
            <item id=\"cover-image\" href=\"images/" . $coverFilename . "\" media-type=\"image/jpeg\"/>
            <item id=\"cover\" href=\"cover.xhtml\" media-type=\"application/xhtml+xml\"/>
            <item id=\"nav\" href=\"nav.xhtml\" media-type=\"application/xhtml+xml\"/>
            <item id=\"content\" href=\"content.xhtml\" media-type=\"application/xhtml+xml\"/>
        </manifest>
        <spine toc=\"ncx\">
            <itemref idref=\"cover\"/>
            <itemref idref=\"nav\"/>
            <itemref idref=\"content\"/>
        </spine>
    </package>
    ");
    // Create content XHTML file
    file_put_contents($contentDir . "content.xhtml", 
    "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
    <!DOCTYPE html>
    <html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"en\">
    <head><title>".$title."</title><link href=\"css/epub.css\" rel=\"stylesheet\" type=\"text/css\"/>
		<link href=\"css/nav.css\" rel=\"stylesheet\" type=\"text/css\"/><meta charset=\"UTF-8\"/></head>
    <body>
        <h1>".$title."</h1>
        <h2>".$author."</h2>
        ".$body."
    </body>
    </html>
    ");

    file_put_contents($contentDir . "toc.ncx",
    "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<ncx xmlns=\"http://www.daisy.org/z3986/2005/ncx/\"
     version=\"2005-1\">
  <head>
    <meta name=\"dtb:uid\" content=\"urn:uuid:12345678-1234-1234-1234-123456789abc\"/>
    <meta name=\"dtb:depth\" content=\"1\"/>
    <meta name=\"dtb:totalPageCount\" content=\"0\"/>
    <meta name=\"dtb:maxPageNumber\" content=\"0\"/>
  </head>
  <docTitle>
    <text>".$title."</text>
  </docTitle>
  <navMap>
    <navPoint id=\"navPoint-1\" playOrder=\"1\">
      <navLabel>
        <text>Cover</text>
      </navLabel>
      <content src=\"cover.xhtml\"/>
    </navPoint>
    <navPoint id=\"navPoint-2\" playOrder=\"2\">
      <navLabel>
        <text>Index</text>
      </navLabel>
      <content src=\"nav.xhtml\"/>
    </navPoint>
    <navPoint id=\"navPoint-3\" playOrder=\"3\">
      <navLabel>
        <text>Body</text>
      </navLabel>
      <content src=\"content.xhtml\"/>
    </navPoint>
  </navMap>
</ncx>
    ");

    // Create EPUB file (ZIP Archive)
    $epubFile = "book.epub";
    $zip = new ZipArchive();

    //Zips together all files in buildEPUB dir
    if ($zip->open($epubFile, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {

        $zip->addFile($buildDir . "mimetype", "mimetype");
        $zip->setCompressionName("mimetype", ZipArchive::CM_STORE); // No compression
        // Add mimetype first and uncompressed
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(realpath("buildEPUB"), RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($buildDir));
            $zip->addFile($filePath, str_replace("tml/buildEPUB/", "",$relativePath));
        }
    $zip->close();

    //Packages and send out zip file as download
    header('Content-Type: application/epub+zip');
    header('Content-Disposition: attachment; filename="book.epub"');
    header('Content-Length: ' . filesize($epubFile));
    EXEC_SQL("insert into log (function, logdata) values (?,?)", "buildBook", "{status : 0}");
    EXEC_SQL("insert into books (promptid, author, title, body) values (?,?,?,?)", $_POST["promptid"], $_POST["author"], $_POST["title"], $_POST["body"]);
    readfile($epubFile);

    // Delete files used to make EPub
    $delete = function(string $dir): void {
      $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
      $files = new RecursiveIteratorIterator($it,
                  RecursiveIteratorIterator::CHILD_FIRST);
      foreach($files as $file) {
          if ($file->isDir()){
              rmdir($file->getPathname());
          } else {
              unlink($file->getPathname());
          }
    }
  };
    $delete($buildDir);
    rmdir($buildDir);
    unlink("book.epub");
    
    exit;
    } else {
        return "fail";
    }
    } catch (Throwable $e) {
        EXEC_SQL("insert into log (function, logdata) values (?,?)", "buildBook", $e->getFile() . "-" .$e->getLine() . ":" . $e->getMessage());
    }
  }

  /**
   * @api GET /final.php/getBooks
   * @apiName getBooks
   * @apiDescription Retrieve list of generated eBooks
   *
   * @apiParam {String} [author] Optional: Filter by author name
   * @apiParam {String} [title] Optional: Filter by title
   * @apiParam {String} [body] Optional: Filter by body content
   *
   * @apiSuccess {Integer} status Status code (0 = success)
   * @apiSuccess {String} message Success message
   * @apiSuccess {Array} result Array of book records
   *
   * @apiError {Integer} status Error status code (1)
   * @apiError {String} message Error description
   */
  public static function getBooks() {
    try {
      $where = "";
      $params = [];
      if (!empty($_REQUEST['author'])) {
        $where = "WHERE author LIKE ?";
        $params[] = "%" . trim($_REQUEST['author']) . "%";
      } else if (!empty($_REQUEST['title'])) {
        $where = "WHERE title LIKE ?";
        $params[] = "%" . trim($_REQUEST['title']) . "%";
      } else if (!empty($_REQUEST['body'])) {
        $where = "WHERE body LIKE ?";
        $params[] = "%" . trim($_REQUEST['body']) . "%";
      }
      $sql = "SELECT bookid, promptid, timestamp, author, title, body FROM books $where ORDER BY bookid DESC";
      $retData["result"] = GET_SQL($sql, ...$params);
      $retData["status"] = 0;
      $retData["message"] = "Books fetched successfully";
      EXEC_SQL("insert into log (function, logdata) values (?,?)", "getBooks", "{status : 0}");
    } catch (Throwable $e) {
      $retData["status"] = 1;
      $retData["message"] = $e->getMessage();
      EXEC_SQL("insert into log (function, logdata) values (?,?)", "getBooks", $e->getMessage());
    }
    header('Content-Type: application/json');
    return json_encode($retData);
  }

  /**
   * @api GET /final.php/getCost
   * @apiName getCost
   * @apiDescription Retrieve API call cost data and statistics
   *
   * @apiParam {String} [prompt] Optional: Filter by prompt text
   *
   * @apiSuccess {Integer} status Status code (0 = success)
   * @apiSuccess {String} message Success message
   * @apiSuccess {Array} result Array of cost records
   * @apiSuccess {Integer} totalcost Total tokens used across all calls
   * @apiSuccess {Integer} totalcalls Total number of API calls
   *
   * @apiError {Integer} status Error status code (1)
   * @apiError {String} message Error description
   */
  public static function getCost() {
    try {
      $where = "";
      $params = [];
      if (!empty($_REQUEST['prompt'])) {
        $where = "WHERE prompt LIKE ?";
        $params[] = "%" . trim($_REQUEST['prompt']) . "%";
      }
      $sql = "SELECT promptid, tokens, prompt, timestamp FROM cost $where ORDER BY promptid DESC";
      $retData["result"] = GET_SQL($sql, ...$params);
      $retData["totalcost"] = GET_SQL("SELECT SUM(tokens) AS total_tokens FROM cost")[0]['total_tokens'];
      $retData["totalcalls"] = GET_SQL("SELECT COUNT(*) AS total_calls FROM cost")[0]['total_calls'];
      $retData["status"] = 0;
      $retData["message"] = "Cost data fetched successfully";
      EXEC_SQL("insert into log (function, logdata) values (?,?)", "getCost", "{status : 0}");
    } catch (Throwable $e) {
      $retData["status"] = 1;
      $retData["message"] = $e->getMessage();
      EXEC_SQL("insert into log (function, logdata) values (?,?)", "getCost", $e->getMessage());
    }
    header('Content-Type: application/json');
    return json_encode($retData);
  }


}
