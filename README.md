# SumoCoders Application Skeleton

Use the following commands to create a new project:

    composer create-project sumocoders/application-skeleton my_project
    git init
    git add .
    git commit -n -m "Initial commit"
    
Start you project by running:

    symfony server:start
    npm run-script watch

    
## Configuration
### Deployment
Open `deploy.php` and check the configuration, replace the example values 
(prefixed with `$`) with correct values.

Try to deploy to staging by running:

    symfony php vendor/bin/dep deploy staging
    
    
Log in thru `ssh` on the dev-server and alter the `.env.local`-file to use the
correct credentials.


### Continuous deployment to staging
Each time something is merged into the staging branch it can be deployed 
automatically. To do so, follow the steps below:

1. Open the project in Gitlab.
2. Open Settings → Repository → Deploy Keys.
3. Click the tab "Privately accessible deploy keys" and enable the key called 
   "Sumo deploy user".
4. Open Settings → CI / CD → Variables.
5. Add a variable called `SSH_PRIVATE_KEY`, the value can be found in 1Password
   under "Sumo Deploy User private key". You can check the "Protect variable" 
   flag.
5. Add a variable called `SSH_KNOWN_HOSTS`, the value should be the output of 
    `ssh-keyscan -H dev02.sumocoders.eu`.
6. Open `.gitlab-ci.yaml`, scroll to `Deploy - to staging`.
7. Alter the url under `environment → url`.

    
## Usage
### Using Encore

Building assets:

    # compile assets once
    npm run-script dev
    
    # or, recomile assets automatically when files change
    npm run-script watch
    
    # on deploy, create a production build
    npm run-script build

For more information about Encore, see the [official documentation](https://symfony.com/doc/current/frontend.html#webpack-encore).


## Working on the Skeleton
### Testing `create-project` locally

There is a test-script `scripts/test.sh` which you can use:

    COMPOSER_MEMORY_LIMIT=-1 ./scripts/test.sh BRANCH-TO_TEST TARGETDIR

This will create a new project that uses the commited code in the selected branch.
The new project will be located in the TARGETDIR

Kudos to [beporter](https://gist.github.com/beporter/31e7d1f5beeffda0da94).

## Tests
We use [panther](https://github.com/symfony/panther) to add functional tests to our project.
By default a page response 200 should be tested on al pages. To do this you can add your urls to the `providePublicUrls` and/or `provideLoggedInUrls`.


