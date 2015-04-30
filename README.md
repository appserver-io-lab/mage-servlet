# Magento Servlet Implementation

This is minimalistic servlet implementation to run Magento as servlet in the
servlet engine.

## Issues
In order to bundle our efforts we would like to collect all issues regarding this package in [the main project repository's issue tracker](https://github.com/appserver-io/appserver/issues).
Please reference the originating repository as the first element of the issue title e.g.:
`[appserver-io/<ORIGINATING_REPO>] A issue I am having`

## Usage

To activate the servlet create a file called WEB-INF/web.xml in your Magento
root directory and copy the following content into this file.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<web-app version="1.0">

    <display-name>Mage Servlet</display-name>
    <description>Mage Servlet</description>

    <servlet>
        <description/>
        <display-name>MageServlet</display-name>
        <servlet-name>MageServlet</servlet-name>
        <servlet-class>AppserverIo\Lab\MageServlet\MageServlet</servlet-class>
    </servlet>

    <servlet-mapping>
        <servlet-name>MageServlet</servlet-name>
        <url-pattern>/</url-pattern>
    </servlet-mapping>

    <servlet-mapping>
        <servlet-name>MageServlet</servlet-name>
        <url-pattern>/*</url-pattern>
    </servlet-mapping>

</web-app>
```

After storing the file and restarting the appserver open your browser enter
the URL ```http://127.0.0.1:9080/magento/index.do``` to run Magento using 
the servlet instead of the FastCGI or PHP module. 
