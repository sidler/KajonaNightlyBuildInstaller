<?php
/*

The MIT License (MIT)

Copyright (c) 2015 Stefan Idler, Idler IT-Services, http://www.mulchprod.de

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

 */


// Config Params
const DOWNLOAD_WEBSITE = "https://www.kajona.de/nightly-builds.html";
const DOWNLOAD_FILE_REGEX = '#<a href="(([a-zA-Z0-9/_\-\:\.])*(\.zip))+">(kajona_v4_allinone_([0-9\-])*\.zip)</a>#i';
// /Config Params



echo "Kajona Nightly Build Installer\n";
echo "(c) sidler@kajona.de";
echo "\n\n";

echo "Checking kajona.de for the latest nightly build\n";
$strSite = file_get_contents(DOWNLOAD_WEBSITE);
if($strSite === false || $strSite == "") {
    echo "Error fetching the nightly-builds page\n";
    return;
}

$arrLinks = array();
if(preg_match(DOWNLOAD_FILE_REGEX, $strSite, $arrLinks) == 0) {
    echo "Error fetching the latest download-url\n";
    return;
}

$strZipname = $arrLinks[4];
$strDownloadURL = $arrLinks[1];

echo "Found download url: ".$strDownloadURL."\n";
echo "Filename: ".$strZipname."\n";

if(is_file(__DIR__."/".$strZipname)) {
    echo "File already downloaded, skipping download\n";
}
else {
    echo "Starting download\n";
    file_put_contents(__DIR__."/".$strZipname, file_get_contents($arrLinks[1]));

    if(!is_file(__DIR__."/".$strZipname)) {
        echo "Download failed!";
        return;
    }
}

echo "Checking for present kajona-folder\n";
if(is_dir(__DIR__."/kajona")) {
    echo "Renaming existing kajona-folder\n";
    if(!rename(__DIR__."/kajona", __DIR__."/kajona_".time())) {
        echo "Failed to rename existing kajona folder, aborting.\n";
        return;
    }
}

echo "Unzipping file...\n";
$objZip = new ZipArchive;
$objOpen = $objZip->open(__DIR__."/".$strZipname);
if($objOpen === TRUE) {
    $objZip->extractTo(__DIR__);
    $objZip->close();
    echo "... unzip successful\n";

} else {
    echo "... failed to unzip.\n";
    return;
}

echo "Setting up chmod...\n";
chmod(__DIR__."/kajona/project/system/config", 0777);
chmod(__DIR__."/kajona/project/log", 0777);
chmod(__DIR__."/kajona/project/dbdumps", 0777);
chmod(__DIR__."/kajona/project/temp", 0777);
chmod(__DIR__."/kajona/templates", 0777);
chmod(__DIR__."/kajona/templates/default", 0777);
chmod(__DIR__."/kajona/files/cache", 0777);
chmod(__DIR__."/kajona/files/images", 0777);
chmod(__DIR__."/kajona/files/public", 0777);
chmod(__DIR__."/kajona/files/downloads", 0777);
chmod(__DIR__."/kajona/core", 0777);

echo "Creating a new config.php...\n";

$strConfig = <<<PHP
<?php
    \$config['dbhost']               = 'n.a.';
    \$config['dbusername']           = 'n.a.';
    \$config['dbpassword']           = 'n.a.';
    \$config['dbname']               = 'kajona';
    \$config['dbdriver']             = 'sqlite3';
    \$config['dbprefix']             = 'kajona_';
    \$config['dbport']               = 'n.a.';
PHP;

file_put_contents(__DIR__."/kajona/project/system/config/config.php", $strConfig);

chdir(__DIR__."/kajona");
echo "Including boostrap.php\n";
require_once __DIR__.'/kajona/core/bootstrap.php';

//fetch all output
ob_start();
require_once __DIR__.'/kajona/core/module_installer/installer.php';
$strData = ob_get_clean();
//and remove it :)
@ob_end_clean();

echo "Setting up admin-account\n";
class_carrier::getInstance()->getObjSession()->setSession("install_username", "admin");
class_carrier::getInstance()->getObjSession()->setSession("install_password", "demo");
class_carrier::getInstance()->getObjSession()->setSession("install_email", "demo@kajona.de");


echo "Calling all in one installer\n";
$objInstaller = new class_installer();
$objInstaller->processAutoInstall();
chmod(__DIR__."/kajona/project/dbdumps/kajona.db3", 0777);

echo "\n";
echo "\n";
echo "Finished!\n";
echo "Open your browser and log in:\n\n";
echo "Username: admin\n";
echo "Password: demo\n";
echo "\n";
echo "Have fun using Kajona!\n";