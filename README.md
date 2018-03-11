STUBLE
========================================

Stuble is command line tool built with PHP to simplify working with stubs.
Stuble will collect parameters in your stub(s) file(s) and ask you that paremeters.
So you don't need to make processing scripts for each stub file.

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

(soon)