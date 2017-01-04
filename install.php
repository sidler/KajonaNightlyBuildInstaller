<?php
/*

The MIT License (MIT)

Copyright (c) 2015-2017 Stefan Idler, Idler IT-Services, http://www.mulchprod.de

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
const DOWNLOAD_FILE_REGEX = '#<a href="(([a-zA-Z0-9/_\-\:\.])*(\.zip))+">(kajona_v5_allinone_([0-9\-])*\.zip)</a>#i';
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
chmod(__DIR__."/kajona/project/module_system/system/config", 0777);
chmod(__DIR__."/kajona/project/log", 0777);
chmod(__DIR__."/kajona/project/dbdumps", 0777);
chmod(__DIR__."/kajona/project/temp", 0777);
chmod(__DIR__."/kajona/templates", 0777);
//chmod(__DIR__."/kajona/templates/default", 0777);
chmod(__DIR__."/kajona/files/cache", 0777);
chmod(__DIR__."/kajona/files/images", 0777);
chmod(__DIR__."/kajona/files/extract", 0777);
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

file_put_contents(__DIR__."/kajona/project/module_system/system/config/config.php", $strConfig);

chdir(__DIR__."/kajona");
echo "Including boostrap.php\n";
require_once 'phar://'.__DIR__.'/kajona/core/module_system.phar/bootstrap.php';

echo "Setting up admin-account\n";
\Kajona\System\System\Carrier::getInstance()->getObjSession()->setSession("install_username", "admin");
\Kajona\System\System\Carrier::getInstance()->getObjSession()->setSession("install_password", "demo");
\Kajona\System\System\Carrier::getInstance()->getObjSession()->setSession("install_email", "demo@kajona.de");




echo "Searching for packages to be installed...\n";
$objManager = new \Kajona\Packagemanager\System\PackagemanagerManager();
$arrPackageMetadata = $objManager->getAvailablePackages();

$arrPackagesToInstall = array();
foreach($arrPackageMetadata as $objOneMetadata) {
    if(!in_array($objOneMetadata->getStrTitle(), array("samplecontent")))
        $arrPackagesToInstall[] = $objOneMetadata;
}

echo "Number of packages found: ".count($arrPackagesToInstall)."\n";

$intMaxLoops = 0;
while(count($arrPackagesToInstall) > 0 && ++$intMaxLoops < 100) {
    foreach($arrPackagesToInstall as $intKey => $objOneMetadata) {

        if(!$objOneMetadata->getBitProvidesInstaller()) {
            unset($arrPackagesToInstall[$intKey]);
            continue;
        }

        echo "Installing ".$objOneMetadata->getStrTitle()."...\n";
        $objHandler = $objManager->getPackageManagerForPath($objOneMetadata->getStrPath());

        if(!$objHandler->isInstallable()) {
            continue;
        }

        $objHandler->installOrUpdate();
        unset($arrPackagesToInstall[$intKey]);
    }
}


echo "Installing samplecontent...\n";
foreach (\Kajona\Installer\System\SamplecontentInstallerHelper::getSamplecontentInstallers() as $objOneInstaller) {
    if (!$objOneInstaller->isInstalled()) {
     	\Kajona\Installer\System\SamplecontentInstallerHelper::install($objOneInstaller);  
     	echo "Installing ".get_class($objOneInstaller)."\n";          
    }
}




try {
    $objHandler = $objManager->getPackageManagerForPath(class_resourceloader::getInstance()->getCorePathForModule("module_samplecontent")."/module_samplecontent");
}
catch(Kajona\System\System\Exception $objEx) {
    $objHandler = null;
}
if($objHandler != null && $objHandler->isInstallable())
    $strReturn .= $objHandler->installOrUpdate();



chmod(__DIR__."/kajona/project/dbdumps/kajona.db3", 0777);

echo "\n";
echo "\n";
echo "Finished!\n";
echo "Open your browser and log in:\n\n";
echo "Username: admin\n";
echo "Password: demo\n";
echo "\n";
echo "Have fun using Kajona!\n";
