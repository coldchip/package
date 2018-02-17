<?php

// error_reporting(0);

class Compile {
	
	public $setOutput = "";
	
	private $CHUNK_SIZE = 32768;
	
	private function encodeMainHeader() {
		$data = "CHIPFS";
		$data .= hex2bin("0000");
		$data .= hex2bin(hash("sha512", uniqid()));
		return str_pad($data, 1024, hex2bin("00"));
	}
	
	private function decodeMainHeader($data) {
		$data = bin2hex($data);
		$signature = substr($data, 0, 16);
		return array("signature" => $signature);
	}
	
	private function encrypt($data) {
		return gzdeflate($data, -1);
	}
	
	private function decrypt($data) {
		return gzinflate($data);
	}
	
	public function buildFrom($input) {
		if(is_dir($input)) {
			$output = fopen($this->setOutput, "wb");
			fwrite($output, $this->encodeMainHeader());
			
			$manifest = ($this->genManifest($input, $output, ""));
			
			fclose($output);
			
		} else {
			$this->throwError();
		}
	}
	
	private function genManifest($inputDir, $outputDest, $currentFolder) {
		$scanArray = scandir($inputDir, 0);
		foreach($scanArray as $scanData) {
			if($scanData !== "." && $scanData !== "..") {
				if(is_dir($inputDir . "/" . $scanData)) {
					fwrite($outputDest, $this->generateHeader(array("type" => "folder", "path" => $this->pathSplit($currentFolder . "/" . $scanData))));
					$this->genManifest($inputDir . "/" . $scanData, $outputDest, $currentFolder . "/" . $scanData);
				} else {
					$readFile = fopen($inputDir . "/" . $scanData, "r");
					$fileSize = fileSize($inputDir . "/" . $scanData);
					fwrite($outputDest, $this->generateHeader(array("type" => "file", "path" => $this->pathSplit($currentFolder . "/" . $scanData))));
					while(!feof($readFile)) {
						// error_log("Compressing... ");
						$readData = $this->encrypt(fread($readFile, $this->CHUNK_SIZE));
						$readDataSize = str_pad((strlen(bin2hex($readData)) / 2), 16, hex2bin("00"));
						fwrite($outputDest, $readDataSize . $readData);
					}
					fclose($readFile);
				}
			}
		}
		return $data;
	}
	
	public function decompressFrom($input) {
		$readData = fopen($input, "rb");
		$header = $this->decodeMainHeader(fread($readData, 1024));
		if($header["signature"] == "4348495046530000") {
			while(ftell($readData) < fileSize($input)) {
				$headerSize = $this->removePadding(fread($readData, 32));
				$headerData = json_decode($this->removePadding(fread($readData, $headerSize)), true);
				if($headerData["type"] == "folder") {
					@mkdir($this->setOutput . "/" . $this->pathJoin($headerData["path"]));
				}
				if($headerData["type"] == "file") {
					$writeOutput = fopen($this->setOutput . "/" . $this->pathJoin($headerData["path"]), "w+");
					while(true) {
						$chunkSize = $this->removePadding(fread($readData, 16));
						$decryptedData = $this->decrypt(fread($readData, $chunkSize));
						fwrite($writeOutput, $decryptedData);
						if((strlen(bin2hex($decryptedData)) / 2) < $this->CHUNK_SIZE) {
							break;
						}
					}
					fclose($writeOutput);
				}
			}
			fclose($readData);
		} else {
			$this->throwError();
		}
	}
	
	private function pathSplit($path) {
		$path = str_split($path);
		if($path[0] == "/") {
			unset($path[0]);
		}
		if(end($path) == "/") {
			array_pop($path);
		}
		$path = implode("", $path);
		$path = preg_replace("/(\/\/+)/", "/", $path);
		$path = preg_replace("/(\.\.+)/", ".", $path);
		return json_encode(explode("/", $path));
	}
	
	private function generateHeader($headerData) {
		$headerData = json_encode($headerData);
		return str_pad(strlen($headerData), 32, hex2bin("00")) . $headerData;
	}
	
	private function pathJoin($path) {
		$path = json_decode($path, true);
		return implode("/", $path);
	}
	
	private function removePadding($chunk) {
		return str_replace(hex2bin("00"), "", $chunk);
	}
	
	private function throwError() {
		die("[ERROR]");
	}
	
	private function verbose($data) {
		echo("<pre>");
		var_export($data, false);
		echo("</pre>");
	}
}

$cp = new Compile();

$cp->setOutput = $_SERVER["DOCUMENT_ROOT"] . "/compile/out.cfs";

$cp->buildFrom($_SERVER["DOCUMENT_ROOT"] . "/login/");

// ********************************************************************

$cp->setOutput = $_SERVER["DOCUMENT_ROOT"] . "/testfol";

$cp->decompressFrom($_SERVER["DOCUMENT_ROOT"] . "/compile/out.cfs");



?>
