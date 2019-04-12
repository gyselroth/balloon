# Desktop client

## Windows 
Go grab the latest [release](https://github.com/gyselroth/balloon-client-desktop/releases/latest) and download the .exe desktop client. You can install the exe either as non-administrator into your profile or as administrator system wide.
The desktop client does register itself as auto start application after it was started for the first time.
It does automatically check for updates and install them after either restarting the client or manually executed.

## Mac OS
Go grab the latest [release](https://github.com/gyselroth/balloon-client-desktop/releases/latest) and download the .dmg desktop client. You need administrator privileges to drop the application into the program folder or alternatively as non-administrator drop it into your profile.
The desktop client does register itself as auto start application after it was started for the first time.
It does automatically check for updates and install them after either restarting the client or manually executed.

## Linux (Deb based distribution)
You can download the deb file directly from the latest [release](https://github.com/gyselroth/balloon-client-desktop/releases/latest). But you won't receive any updates since automatic updates on debian get distributed via an apt repository.

### Install via apt (Recommended)
The recommended way is to install the client via the apt repository:
```bash
echo "deb https://dl.bintray.com/gyselroth/balloon stable main" | sudo tee -a /etc/apt/sources.list
wget -qO - https://bintray.com/user/downloadSubjectPublicKey?username=gyselroth | sudo apt-key add -
sudo apt-get update
sudo apt-get install balloon-desktop
```

### Install unstable packages via apt
If you want using nightly or unstable packages you can install those repositories. Those repositories are not meant for production, use at own risk!
```bash
echo "deb https://dl.bintray.com/gyselroth/balloon unstable main" | sudo tee -a /etc/apt/sources.list
```

or for nightly:
```bash
echo "deb https://dl.bintray.com/gyselroth/balloon nightly main" | sudo tee -a /etc/apt/sources.list
```

## Linux (Rpm based distribution)
For rpm based distributions you are required to grab the rpm from  the lastest [release](https://github.com/gyselroth/balloon-client-desktop/releases/latest). 
There is not (yet) a yum repository.

## Linux (Others)
If you do not want a packaged bundle or not having an rpm/deb based distribution, you may grab the zipped archive from the latest [release](https://github.com/gyselroth/balloon-client-desktop/releases/latest).
