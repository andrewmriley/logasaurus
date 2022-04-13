Installation
===

1. Composer require
```shell
$ composer require andrewmriley/logasaurus
```

2. Create a `.logasaurus.yml` file in the root of your project with the format:

```yaml
changelogFile: changelogfilename
finalize: true/false
filesPath: path/to/files/to/read/
```

### How to run:

```shell
$ ./vendor/bin/logasaurus generate versionnumber [optional date]
```

example:
```shell
$ logasaurus generate 1.01.00
```
