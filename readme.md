# Grovers\Deployer

This project handles the POST data from Bitbucket when a WebHook is fired, and then calls the appropriate job in a Jenkins server.

Bitbucket has a great webhook that can be used to trigger events when a GIT repository is updated.  Unfortunately the same webhook will be fired for all branches.  If you have created an environment aware application, you'll often have one set of deploy instructions for your staging server, and another set of instructions for the production server.  And if your branches correspond to the environment (i.e. master = production, develop = staging), then the webhook is a great start, but not quite sufficient.

This project fills that gap.  It allows different tasks to be executed when the different branches are updated.  To do this an assumption is made that your Jenkins build job will be named after your repository and branch with this format "repoName-branchName".

## Setup

### Jenkins

1. Install the [**Build Token Root Plugin**](https://wiki.jenkins-ci.org/display/JENKINS/Build+Token+Root+Plugin)
2. All build jobs are expected to be named in the following format `repoName-branchName`.  Example, if your repository is named "mywebsite", and it has the standard "master" and "develop" branches, then the corresponding Jenkins jobs would be "mywebsite-master" and "mywebsite-develop".
3. Use the "Trigger Builds Remotely" option in the "Build Triggers" section.
4. Assign an Authentication Token.  This is a totally arbitrary string, but I prefer to use a strong password of 20 characters (or more).  This token will help ensure only authorized requests to your build jobs can be made.
5. Other than these points, configure your build jobs (or Jenkins) as you normally would.

### Bitbucket

1. Open your project in Bitbucket.
2. Goto Settings -> Webhooks
3. Add a webhook
4. Give your webhook a title - "Jenkins" may be a reasonable name.
5. Enter the URL to the page this package is being used on.  For instance, I have created a subdomain called "deployit.mydomain.com".  This package is called from the index.php file at that domain.  In addition, this is where the Authentication Token created in Jenkins comes in.  So the final URL would be in the form of `http://deployit.mydomain.com?token=ABCDEFG1234567890` (where the token matches what you have set up for your job.)
6. Select **Choose from a full list of triggers**.  It is recommended that PUSH and MERGED be selected at a minimum

The idea is that Bitbucket will call the same URL with the same token for all branches of your repository.  Additional data is passed along with the webhook POST that specifies your repository, the branch involved, dates, comments, etc.  

The URL needs to be publicly accessible, but we don't random strangers triggering our build jobs.  This is why the token is used.  This package will extract the required data, determine the name of the Jenkins build job to call, then call it (via the URL made available by the Build Token Root Plugin).

## Usage
This is a standard composer package, and does not rely on any frameworks.  It can be used within frameworks if desired, or as a standalone PHP file.

Install with the usual
```
composer require grovers/deployer dev-develop
```

Call the deployer in your code
```
<?php
    require 'vendor/autoload.php';
    $deployer = new Grovers\Deployer('http://myJenkinsServer.com');

    // Log raw data (default is false)
    $deployer->rawdata(true);

    // Handle the incoming request
    $deployer->process();
```

The code will quietly exit if the current request is not an HTTP POST request.

The rawdata(true) will dump the posted data as a JSON string into your logs.  This is VERY helpful for debugging purposes, but will make for large logs if left enabled.

A "deployer.log" file will be created at the document root of the domain being called.
