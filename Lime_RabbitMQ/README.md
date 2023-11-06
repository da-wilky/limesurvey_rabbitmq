# LimeSurvey RabbitMQ Plugin

Send the SurveyID to RabbitMQ after completion. RabbitMQ can provide flexible further handling.

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