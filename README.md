# SumoCoders Application Skeleton

Use the following commands to create a new project:

    composer create-project sumocoders/application-skeleton my_project
    git init
    git add .
    git commit -n -m "Initial commit"
    
Start your project by running:

    symfony serve
    symfony console sass:build --watch

## Configuration
### Deployment
Open `deploy.php` and check the configuration, replace the example values 
(prefixed with `$`) with correct values.

Try to deploy to staging by running:

    symfony php vendor/bin/dep deploy stage=staging
    
Log in through `ssh` on the dev-server and alter the `.env.local`-file to use the
correct credentials.

### Continuous deployment to staging and production
Each time something is merged into the staging/master branch it will be deployed 
automatically. 

1. Open `.gitlab-ci.yaml`
2. Scroll to `Deploy - to staging`.
3. Alter the url under `environment → url`.
4. Scroll to `Deploy - to production`.
5. Alter the url under `environment → url`.
    
## Working on the Skeleton
### Testing `create-project` locally

There is a test-script `scripts/test.sh` which you can use:

    COMPOSER_MEMORY_LIMIT=-1 ./scripts/test.sh BRANCH-TO_TEST TARGETDIR

This will create a new project that uses the commited code in the selected branch.
The new project will be located in the TARGETDIR

Kudos to [beporter](https://gist.github.com/beporter/31e7d1f5beeffda0da94).
