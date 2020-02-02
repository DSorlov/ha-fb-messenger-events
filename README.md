# Incomming messages for Home Assistant from Facebook Messenger

Raise events on Home Assistant via incomming messages from Facebook Messenger from approved users.
There is a simple association security mechanism but otherwise most processing is done inside home assistant.

The script raises either a `messenger.command` or `messenger.conversation` event containing the incomming data.
Responses should be sent as normal using the facebook integration below.

## Instructions
1. Create an application as outlined in https://www.home-assistant.io/integrations/facebook/ 
2. Put the PHP file on an accessible https server and set the url in the application created in step 1
3. Configure authentication in the php file and create password to associate
4. Talk to the bot use `/whoami` to get your id, `/authorize <password>` to add as trusted user
