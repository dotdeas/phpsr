phpSR
=====

A opensource and free to use PHP script for generating SafeCom reports in CSV format

## Requirements
* PHP 5.0 or newer with CLI support
* ODBC extension enabled

## Download
You can download the newest release at http://github.com/dotdeas/phpsr/releases/

If you prefer to follow the git repository, the following branch and tag names may be of interest
* ``master`` is the current stable release
* ``trunk`` is the development branch

## Usage
```
Usage: php phpsc.php [OPTION] ...

  -h    print this help
  -d    odbc connection name
  -c    currency
  -o    output filename
  -r    report to use (see list below)
  -s    startdate (yyyy-mm-dd)
  -e    enddate (yyyy-mm-dd)

Reports:
  1 - Cost code printing
  2 - Cost code printing (detailed)
  3 - Cost code printing (less)
```

## Contact me
If you found a bug, got a great idea or just want to say hello. Send me a email on andreas@dotdeas.se

## License
Released under the [MIT license](http://makesites.org/licenses/MIT)
