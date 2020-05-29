# SumoCoders Application Skeleton

Use the following commands to create a new project:

    composer create-project sumocoders/application-skeleton my_project
    
## Usage

### Testing `create-project`

There is a test-script `scripts/test.sh` which you can use:

    COMPOSER_MEMORY_LIMIT=-1 ./scripts/test.sh BRANCH-TO_TEST TARGETDIR

This will create a new project that uses the commited code in the selected branch.
The new project will be located in the TARGETDIR

Kudos to [beporter](https://gist.github.com/beporter/31e7d1f5beeffda0da94).


### Using Encore

Building assets:

    # compile assets once
    npm run-script dev
    
    # or, recomile assets automatically when files change
    npm run-script watch
    
    # on deploy, create a production build
    npm run-script build

For more information about Encore, see the [official documentation](https://symfony.com/doc/current/frontend.html#webpack-encore).
