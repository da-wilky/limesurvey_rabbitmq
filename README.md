# LimeSurvey RabbitMQ Plugin

Send the SurveyID to RabbitMQ after completion. RabbitMQ can provide flexible further handling.

In my case I use the surveyID externally to request limesurvey for survey data & responses and upload those to nextcloud.
It is also possible to directly upload them on click, but then the user has to wait for the upload of the file to finish. That way he only needs to wait for a minimal text message to send.

## Plugin Installation

### Download Zip-File
- Download prepacked zip file from releases (not done yet)
- Upload ZIP file to Limesurvey Plugins on Webpage

### Clone 
- Clone this repository
- use `docker compose up && docker compose down`
- the ZIP file gets created inside the output folder
- Upload ZIP file to Limesurvey Plugins on Webpage

## Configuration

### RabbitMQ

You can set the RabbitMQ connection configuration inside the plugin settings.

### Surveys

Within the settings of a survey you can go under `simple plugins` and activate the feature for the surveys individually. On default it's deactivated for all surveys.