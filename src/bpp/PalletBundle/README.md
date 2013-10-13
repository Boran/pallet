Tools for showing how to optimally stack reels on a pallet

Preparation:
* Install PHP,
* ImageMagick
On linux
  apt-get install imagemagick php5-dev libmagick9-dev
  pecl install imagick
  vi /etc/php5/conf.d/imagick.ini
        extension=imagick.so
  /etc/init.d/apache2 restart

On windows:
http://imagemagick.org/script/binary-releases.php#windows
http://www.elxsy.com/2009/07/installing-imagemagick-on-windows-and-using-with-php-imagick/
DLL:
http://www.peewit.fr/imagick/