# OrangeHRM-Taiga-Integration
Using this webhook, you can easily pull projects, members for the project, user stories, tasks, issues and automatically add those in OrangeHRM. This is automatically create project once it is created in Taiga.

Make sure that the email address for the member of the project is same in taiga as that entered in primary email address in OrangeHRM
Make sure that the customer/client is specific as "Client" in Taiga so as to make sure that customer/client is not fetched in OrangeHRM under members

TO DO:
Code addition to fetch client/customer member from Taiga and add it in OrangeHRM customer list automatically if not present. Currently OrangeHRM does not provide option to specify email address of the client, with whom we can match from Taiga email for the same customer. So need to have modification at OrangeHRM first before doing this modification in this code

