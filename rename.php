<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require(__DIR__.'/class/AnimeParser.class.php');

?>

<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1">
        <title>Insert title here</title>
        <link rel='stylesheet' type='text/css' href='style.css'/>
    </head>
    <body>
    	<h1>Anime Rename</h1>
    	<h2>Wiki Param�tres</h2>
    	<ul>
    		<li><strong>dir :</strong> D�finit le dossier � explorer. Il faut prendre le dossier parents aux dossiers des animes.</li>
    		<li><strong>resolve :</strong> Tente de trouver les bons num�ros des �pisodes en d�duisant par rapport aux �pisodes existants.</li>
    		<li><strong>run :</strong> Lance le renommage des fichiers.</li>
    	</ul>
    	
    	<?php 
	    	$dir = NULL;
	    	if (isset($_GET['dir']))
	    	{
	    		$dir = $_GET['dir'];
	    	}
	    	if (file_exists($dir) === FALSE)
	    	{
	    		echo 'Dossier invalide';
	    	}
	    	else 
	    	{
	    		$dir = (substr($dir, -1) != '/') ? ($dir.'/') : ($dir);
	    		$list_dirs = array_values(array_diff(scandir($dir), ['..', '.']));
	    		
	    		$parser = new AnimeParser();
	    		foreach ($list_dirs as $d)
	    		{
	    			$parser->add_dir($dir.$d);
	    		}
	    		
	    		$parser->format();
	    	}
	    	
	    	
    	?>
    </body>
</html>
