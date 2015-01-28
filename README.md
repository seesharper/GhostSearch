# GhostSearch #


This is a PHP based solution that adds full text search capabilities to the Ghost blogging platform.

The difference between this and the already established GhostHunter project is that this project uses the actual database to perform the search rather than just using the RSS feed.

The following setup is based on IIS (Internet Information Server), but could just as easily be done with Apache or any other decent web server. Except for the web server setup, there is nothing here that ties this to Windows.


# Step 1 #

Use the Web Platform installer to install PHP on the IIS.

Then you have to enable support for SQLite in the PHP.ini file located in the PHP install folder.

Look for the [ExtensionList] and add the following entry.

	extension=php_sqlite3.dll


## Step 2 ##

Install Ghost according to the [documentation](https://github.com/tryghost/Ghost) 
and place it the following folder.

	/inetpub/wwwroot/blog

## Step 3 ##

We want to make our blog available at the following url.

	http://www.sampledomain.org/blog

Open the config.js file that is located directly under your blog folder.

	/inetpub/wwwroot/blog/config.js

Change the url of the blog

 	// The url to use when providing links to the site, E.g. in RSS and email.
    // Change this to your Ghost blogs published URL.
    url: 'http://www.sampledomain.org/blog',

> Note: If you are serving your blog using nothing but the machine name, this will probably not work. Node/Ghost don't like URL's without dots.



## Step 4 #

Set up a reverse proxy in IIS which basically means an URL rewrite.

The configuration (Web.config) for the rewrite rule is as follows:

	<configuration>
	    <system.webServer>
	        <rewrite>
	            <rules>
	                <rule name="RewriteToNodeRule" stopProcessing="true">
	                    <match url="(.*)" />
	                    <action type="Rewrite" url="http://127.0.0.1:2368/blog/{R:1}" />
	                    <conditions>
	                       <add input="{REQUEST_FILENAME}" pattern="ghostsearch.php" negate="true" />
	                       <add input="{REQUEST_FILENAME}" pattern="ghostsearch.html" negate="true" />
	                       <add input="{REQUEST_FILENAME}" pattern="ghostsearch.js" negate="true" />
	                    </conditions>
	                </rule>
	            </rules>
	        </rewrite>
	    </system.webServer>
	</configuration>

What we are saying here is that we want all request to the blog subdomain to forwarded to the node server except for three files that we will talk about in a minute.

Verify that you now are able to reach the blog 

	http://www.sampledomain.org/blog

  
## Step 5 ##

Copy ghostsearch.php, ghostsearch.js and ghostsearch.html to the blog root folder.

> Note: The ghostsearch.html file is just being used for testing purposes and is not actually needed. 


## Step 6 ##

Open the default.hbs file and add the following line at the bottom of the file along with the other javascript references.

	<script src="/blog/ghostsearch.js"></script>

Next open up the index.hbs file and add the following html

	</div>
		<input type="search" id="ghost_searchinput"/>
        <div id = "ghost_searchresult"/>    
		<br/>  
	</div>

Your index file should now look something like.
	
	...
    <div class="vertical">
        <div class="main-header-content inner">
            <h1 class="page-title">{{@blog.title}}</h1>
            <h2 class="page-description">{{@blog.description}}</h2>
        </div>
		<div>
			<input type="search" id="ghost_searchinput"/>
            <div id = "ghost_searchresult"/>    
			<br/>  
		</div>
    </div>
	...   

> Note: You can put the input element and the result div wherever you want as long they are called ghost_searchinput and ghost_searchresult.

That's it. You should now have a searchable blog.

Happy blogging!!


