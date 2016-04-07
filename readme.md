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
    $deployer = new Grovers\Deployer();


    // Handle the incoming request
    $deployer->process();
```

Finally set up your configuration items.  The [dotenv](https://github.com/motdotla/dotenv) is a good tool for getting this set up.  The values in a `.env` text file are loaded into Environment variables.  We leave it to you to determine how you will get the values into the environment.  See the `example/index.php` for one way to do so. The configuration variables are defined below

The code will quietly exit if the current request is not an HTTP POST request.

A "deployer.log" file will be created at the document root of the domain being called.

## Configuration Variables

Configuration values are expected to be defined as Environment variables.  Specifically the getenv() method is used to retrieve the pertinent values.

|ENV Variable|Value|Description|
|------------|-----|-----------|
|JENKINS_URL|HTTP/HTTPS URL|The Jenkins URL.  i.e. http://jenkins.example.com.  The remaining elements are added to this URL based on the data recevied from Bitbucket.|
|LOG_ENABLE|Boolean|Set to true to enable logging.  A deployer.log file will be created.|
|LOG_RAW|Boolean|If set to true the raw JSON data object received from Bitbucket is added to the log file.  We recommend only enabling this when troubleshooting as the resulting log files can grow very quickly.|
|SMTP_ENABLE|Boolean|If set to true, error messages will be sent to the commit author.|
|SMTP_HOSTNAME|Host|A IP Address or Domain name that points to the server that will send out email messages.|
|SMTP_PORT|Integer|The port number to connect to the mail server with.|
|SMTP_USERNAME|String|The username to authenticate to the mail server with.|
|SMTP_PASSWORD|String|The password to authenticate to the mail server with.|
|SMTP_SENDER_NAME|String|The Name presented as the FROM property for outgoing emails.|
|SMTP_SENDER_EMAIL|String|The Email address presented for the FROM property of the outgoing emails.|

## Troubleshooting

See `https://confluence.atlassian.com/bitbucket/event-payloads-740262817.html#EventPayloads-Push` for details about the structure of the Bitbucket webhook payloads.

Most issues will be in the form of one of these issues:

1. The Bitbucket post never arrives at the Deployer URL.
> Check to ensure the Deployer URL is operational.  Ensure the URL specified for the Bitbucket webhook is correct.
1. The received post data is not in the correct format.
> Did Bitbucket update their system?  If so Deployer may need to be updated to handle the new formats.
> Make sure a Webhook is used with Bitbucket, not the older "service" items.
1. You recieve a 404 when talking to the Jenkins URL
> Make sure the Bitbucket repository and the Jenkins job uses the correct naming format.
> Does Jenkins actually have a job for that repository?
1. You receive a 403 when talking to the Jenkins URL
> Ensure the Token used for the webhook URL matches the Build Trigger Authentication Token in the Jenkins job (under the `Trigger builds remotely` item)

