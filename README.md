STUBLE
========================================

Stuble is command line tool built with PHP to simplify working with stubs.
Stuble will collect parameters in your stub(s) file(s) and ask you that parameters.
So you don't need to write scripts to handle each stub file.

![Stuble](https://raw.githubusercontent.com/emsifa/stuble/master/stuble.png)

## REQUIREMENTS

* PHP >= 7.0
* Composer

## INSTALLATION


```
composer global require emsifa/stuble:dev-master
```

> Make sure you have register composer bin directory to your PATH variable. You can [read this](https://getcomposer.org/doc/03-cli.md#global) for more information.

## USAGE EXAMPLE

Before we get started, you need to know that stuble scan stubs files from 2 locations.

1. Local source: `stubs` directory wherever you want to use `stuble` command.
2. Global source: path that defined in STUBS_PATH variable.

If same stub file found in 2 sources, stuble will use local stub file.

#### Make Stub File

In your cmd/terminal, go to directory wherever you want to try stuble.

For example we are in `/home/me/coding/try-stuble`.

Then create file `stubs/model.stub` in that directory:

```php
<?php

namespace {? model_namespace["App\Models"] ?};

use Illuminate\Database\Eloquent\Model;

/**
 * Model {? entity ?}
 */
class {? entity.pascal ?} extends Model
{

    protected $table = "{? entity.snake.plural ?}";

}

```

#### Generate File From Stub

In that directory (in this example `/home/me/coding/try-stuble`), run command below:

```
stuble create model
```

Then stuble will scan needed parameters in our `stubs/model.stub`,
ask values for that parameters,
then ask again where to put result file.

Try example above, look at the result. You would realize things like:

* First word wrapped by `{?...?}` is a parameter that stuble would ask.
* Words wrapped by `["..."]` like `App\Models` is parameter default value.
* `pascal`, `snake`, `plural` are filters that modify your parameter value.
* You can use `.` to separate each filters (like `snake.plural`).

## THINGS YOU NEED TO KNOW

#### Generate Stubs in a Directory

Just put `path/to/directory` instead `path/to/filename` in `stuble create` command.
Stuble will collect parameters in stubs files inside that directory.
Then generate results files.

For example if your `stubs` directory has structure like this:

```
└── laravel-scaffolds
    ├── controller.stub
    ├── factory.stub
    ├── migration.stub
    ├── model.stub
    ├── resource.stub
    ├── store-request.stub
    └── update-request.stub
```

You can use `stuble create laravel-scaffolds` to generate `controller`, `factory`, `migration`, `model`, `resource`, `store-request`, and `update-request`.

#### Define Output File Path in Stub File

If you have your own standard filepath and don't want stuble ask for output filepath every `stuble create` that stub,
you can add line below at very top of your stub file:

```
## path: define/your/relative/path/here.ext
```

Example:

```php
## path: app/Models/{? entity.pascal ?}.php
<?php

namespace App\Models;

class {? entity.pascal ?} extends Model
{

    protected $table = "{? entity.snake.plural ?}";

}

```

Now if you create file from that stub, stuble won't ask you output filepath.
Instead stuble will automatically put file using that format.

#### Built-in Filters

| **Filter**            | Description                              | Value                            | Result             |
|-----------------------|------------------------------------------|----------------------------------|--------------------|
| **lower**             | Transform value to lower case.           | ProductCategory                  | productcategory    |
| **upper**             | Transform value to upper case.           | ProductCategory                  | PRODUCTCATEGORY    |
| **ucfirst**           | Make first letter capital.               | product category                 | Product category   |
| **ucwords**           | Make first letter in each words capital. | product category                 | Product Category   |
| **kebab**             | Transform value to kebab-case/dash-case. | ProductCategory                  | product-category   |
| **snake**             | Transform value to snake_case.           | ProductCategory                  | product_category   |
| **camel**             | Transform value to camelCase.            | product category                 | productCategory    |
| **pascal**            | Transform value to PascalCase.           | product category                 | ProductCategory    |
| **studly**            | Alias pascal.                            | product category                 | ProductCategory    |
| **title**             | Transform value to Title Case.           | product_category                 | Product Category   |
| **words**             | Transform value to words.                | product_category                 | product category   |
| **plural**            | Transform value to plural form.          | product_category                 | product_categories |
| **singular**          | Transform value to singular form.        | ProductCategories                | ProductCategory    |
| **replace(str, to)**  | Replace string in value.                 | "Foo\Bar\Baz".replace("\\", "/") | Foo/Bar/Baz        |


#### Make Your Own Filter

In this example we will add filter `substr`.

In your `stubs` directory, create file `stuble-init.php`.
Write code below:

```php
<?php

$stuble->filter('substr', function (string $value, int $start, int $length = null) {
    return substr($value, $start, $length);
});

```

Then you can use it like this.

```
// sample.stub

Your param: {? your_param ?}
Your param after substr: {? your_param.substr(0, 6) ?}

```

> You can use that filter in all stubs file inside that directory, including subdirectories too. If you have same filter in subdirectory, stuble will override it.

#### Show List Available Stubs

You can use command `stuble ls` to show list available stubs.

For example:

```bash
# Show list stubs in both global and local sources
stuble ls

# Show list stubs in global source only
stuble ls --global
# or
stuble ls -G

# Show list stubs in local source only
stuble ls --here
# or
stuble ls -H

# Show list stubs contains 'laravel'
stuble ls laravel

# Show list stubs contains 'laravel' in global source only
stuble ls laravel -G

```