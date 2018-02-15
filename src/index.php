<?php

class Compile {
	
	public $setOutput = "";
	
	private function encodeMainHeader() {
		$data = "CHIPFS";
		$data .= hex2bin("0000");
		$data .= hex2bin(hash("sha512", uniqid()));
		//$data .= str_pad(time(), 16, hex2bin("00"));
		return str_pad($data, 1024, hex2bin("00"));
	}
	
	private function decodeMainHeader($data) {
		$data = bin2hex($data);
		$signature = substr($data, 0, 16);
		return array("signature" => $signature);
	}
	
	public function buildFrom($input) {
		if(is_dir($input)) {
			$output = fopen($this->setOutput, "w+");
			
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
				$headerData = array();
				if(is_dir($inputDir . "/" . $scanData)) {
					$headerData["type"] = "folder";
					$headerData["path"] = $this->pathSplit($currentFolder . "/" . $scanData);
					fwrite($outputDest, $this->generateHeader($headerData, $headerData));
					$this->genManifest($inputDir . "/" . $scanData, $outputDest, $currentFolder . "/" . $scanData);
				} else {
					$readFile = fopen($inputDir . "/" . $scanData, "r");
					$headerData = array();
					$headerData["type"] = "file";
					$headerData["paddedSize"] = floor(filesize($inputDir . "/" . $scanData) / 8192) + 1;
					$headerData["size"] = filesize($inputDir . "/" . $scanData);
					$headerData["path"] = $this->pathSplit($currentFolder . "/" . $scanData);
					$headerData["md5"] = md5_file($inputDir . "/" . $scanData);
					fwrite($outputDest, $this->generateHeader($headerData));
					while(!feof($readFile)) {	
						error_log("Compressing: " . $scanData . " " . ftell($outputDest) . "/" . $headerData["size"]);
						fwrite($outputDest, str_pad(fread($readFile, 8192), 8192, hex2bin("00")));
					}
					fclose($readFile);
				}
			}
		}
		return $data;
	}
	
	public function decompressFrom($input) {
		$readData = fopen($input, "r+");
		$header = $this->decodeMainHeader(fread($readData, 1024));
		if($header["signature"] == "4348495046530000") {
			while(!feof($readData))
			{
				$chunkData = fread($readData, 1024);
				$headerData = json_decode($this->removePadding($chunkData), true);
				if($headerData["type"] == "folder") {
					@mkdir($this->setOutput . "/" . $this->pathJoin($headerData["path"]));
				}
				if($headerData["type"] == "file") {
					$writeOutput = fopen($this->setOutput . "/" . $this->pathJoin($headerData["path"]), "w+");
					for($i = 0; $i < $headerData["paddedSize"]; $i++) {
						error_log("Extracting: " . $this->pathJoin($headerData["path"]) . " " . ($i + 1) . "/" . $headerData["paddedSize"]);
						fwrite($writeOutput, fread($readData, 8192));
					}
					ftruncate($writeOutput, $headerData["size"]);
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
		return str_pad(json_encode($headerData), 1024, hex2bin("00"));
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