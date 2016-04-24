<?php
class AnimeParser 
{
    /**
     * Liste des éléments sous forme de pattern à enlever dans le nom d'un anime.
     * @var array
     */
    private $_exclude_words = [];
    
    /**
     * Liste des épisodes impossibles à numéroter.
     * @var array
     */
    private $_conflicts = [];
    
    /**
     * Liste des fichiers
     * @var array
     */
    private $_files = [];
    
    /**
     * Liste de formatage.
     * @var array
     */
    private $_format = [];
    
    /**
     * Nombre total de fichier.
     * @var int
     */
    private $_count = 0;
    
    /**
     * Constructeur
     * @param string $dir Chemin des fichiers à traiter.
     */
    public function __construct($dir=NULL)
    {
        $this->add_dir($dir);
        $config = json_decode(file_get_contents(__DIR__.'/../config.json'));
        $this->_exclude_words = $config->excluded_patterns;
    }
    
    /**
     * Ajoute un dossier à traiter.
     * @param string $dir Chemin des fichiers à traiter.
     * @return boolean
     */
    public function add_dir($dir)
    {
        if (file_exists($dir) && is_dir($dir) && !isset($this->_files[$dir]))
        {
            $dir = (substr($dir, -1) != '/') ? ($dir.'/') : ($dir);
            $this->_files[$dir] = array_diff(scandir($dir), ['..', '.']);
            return TRUE;
        }
        return FALSE;
    }
    
    /**
     * Formate les noms des fichiers.
     */
    public function format()
    {
		ob_start();
        echo '<h2>Détails</h2>';
        $errors = [];
        foreach ($this->_files as $dir => $files)
        {
            echo '<div class="title_file">'.$dir.' : </div>';
            $regex = $this->_regex($dir);
            $anime = basename($dir);
            $this->_format[$anime] = [];
            foreach ($files as $f)
            {
            	if (is_file($dir.$f))
            	{
            		// Si le nom de l'anime ne respecte pas la convention, on le renomme.
	                $ext = $this->_get_ext($f);
	                $name = $this->_reduce($f, $anime);
	                $num = $this->_get_num($name);
	                if (preg_match($regex, $f) == FALSE)
	                {
	                    if ($num === FALSE)
	                    {
	                    	if (isset($_GET['resolve']) && $_GET['resolve'] === '1')
	                    	{
	                    		if (isset($this->_conflicts[$anime]) === FALSE)
	                    		{
	                    			$this->_conflicts[$anime] = [];
	                    		}
	                    		$this->_conflicts[$anime][] = [
		                    		'new' => $dir.$name,
		                    		'old' => $dir.$f,
		                    		'ext' => $ext,
		                    		'num' => $num
	                    		];
	                    	}
	                    	else
	                    	{
	                    		echo '<div class="original">'.$f.'</div>';
	                    		$err = '<div class="error">&#9888; Impossible de trouver le numéro de l\'épisode pour : '.$f.'</div>';
	                    		echo $err;
	                    		$errors[] = $err;
	                    	}
	                        continue;
	                    }
	                    $name = $anime.' '.$num.$ext;
	                    $this->_count++;
	                }
	                else
	                {
	                    $name = $anime.' '.$num.$ext;
	                }
	                $this->_format[$anime][] = [
	                    'new' => $dir.$name,
	                    'old' => $dir.$f,
	                    'num' => $num
	                ];
            	}
            }
            
            // Tentative de résolution des conflits.
            if (isset($_GET['resolve']) && $_GET['resolve'] === '1')
            {
            	$this->_resolve($anime, $dir);
           	}
                  
            // On recherche les épisodes maximal pour le bourage de 0;
            if (isset($this->_format[$anime]))
            {
            	// if ($anime == 'Black Bullet')  ob_clean();
            	$max = 0;
            	foreach ($this->_format[$anime] as $ep)
            	{
            		if (is_array($ep['num']))
            		{
            			foreach ($ep['num'] as $nb)
            			{
            				$nb = number_format($nb);
            				if ($nb > $max)
            				{
            					$max = $nb;
            				}
            			}
            		}
            		else 
            		{
            			$nb = number_format($ep['num']);
            			if ($nb > $max)
            			{
            				$max = $nb;
            			}
            		}
            	}
            	// Avec le numéro de l'épisode maximal, on connait le nombre de bourage à 0.
            	$nb_zero =  strlen($max);
            	$episodes = [];
            	foreach ($this->_format[$anime] as $k => $ep)
            	{
            		$episodes[$k] = [
						'old' => $ep['old']
            		];
            		$episodes[$k]['new'] = $ep['new'];
            		if (is_array($ep['num']) == FALSE)
            		{
            			$ep['num'] = [$ep['num']];
            		}
            		foreach ($ep['num'] as $nb)
            		{
            			$num = sprintf("%0".$nb_zero."d", $nb);
            			$episodes[$k]['new'] = dirname($ep['new']).'/'.preg_replace('#(\d+)\.#', $num.'.', basename($episodes[$k]['new']));
            			$episodes[$k]['num'][] = $nb;
            		}
            		if ($episodes[$k]['old'] !== $episodes[$k]['new'])
            		{
            			$this->_count++;
            			$class = 'warning';
            		}
            		else 
            		{
            			$class = 'good';
            		}
            		echo '<div class="original">'.basename($episodes[$k]['old']).'</div>';
            		echo '<div class="'.$class.'">'.basename($episodes[$k]['new']).'</div>';
            	}
            	$this->_format[$anime] = $episodes;
            }
            // if ($anime == 'Black Bullet')  exit();
        }

        // Récupération des détails d'affichage.
        $details = ob_get_clean();
        
        // Controle et récupération de l'affichage.
        ob_start();
        $this->_control();
        $control = ob_get_clean();
        
        // Affichage du résultat.
        $this->_rename();
        
        // Affichage des erreurs.
        echo '<h2>Formatage</h2><div class="error">'.implode('</div><div class="error">', $errors).'</div>';
        echo $control;
        echo $details;
    }
    
    /**
     * Génère la regex.
     * @param string $dir Chemin des fichiers à traiter.
     * @return string
     */
    private function _regex($dir)
    {
        $anime = basename($dir) ;
        $dirname = addslashes($anime);
        $dirname = str_replace(' ', '\s', $dirname);
        return '#^'.$dirname.'\s\d{1,3}\.\w{2,4}$#';
    }
    
    /**
     * Enlève les éléments pouvant gêner le formatage du nom.
     * @param string $name Nom à réduire.
     * @param string $anime Nom à réduire.
     * @return string
     */
    private function _reduce($name, $anime)
    {
        foreach ($this->_exclude_words as $pattern)
        {
            $name = preg_replace('#'.$pattern.'#i', '', $name);
        }
        $name = preg_replace('#'.preg_quote($anime).'#i', '', $name);
        return $name;
    }
    
    /**
     * Récupère l'extention.
     * @param string $name Nom du fichier.
     * @return string
     */
    private function _get_ext($name)
    {
        return substr($name, strrpos($name, '.'));
    }
    
    /**
     * Récupère le numéro de l'épisode.
     * @param string $name Nom du fichier.
     * @return string
     */
    private function _get_num($name)
    {
        preg_match_all('#\d+#', $name, $num);
        $num = $num[0];
        if (count($num) != 1)
        {
            return FALSE;
        }
        $num = intval($num[0]);
        return ($num < 10) ? ('0'.$num) : ((string)$num);
    }
    
    /**
     * Tente de résoudre les connflits de numéros d'épisode.
     */
    private function _resolve($anime, $dir)
    {
    	if (isset($this->_conflicts[$anime]))
    	{
    		foreach ($this->_conflicts[$anime] as &$conflict)
    		{
    			preg_match_all('#\d+#', basename($conflict['new']), $num);
    			$num = $num[0];
    			// On cherche si l'un des épisodes n'existe pas déjà de manière sûrement identifiée.
    			$final = [];
    			foreach ($num as $n)
    			{
    				reset($this->_format[$anime]);
    				$find = FALSE;
    				while ($find === FALSE && (list(, $ep) = each($this->_format[$anime])))
    				{
    					if ($n == $ep['num'])
    					{
    						$find = TRUE;
    					}
    				}
    				if ($find === FALSE)
    				{
    					$final[] = $n;
    				}
    			}
    			if (count($final) === 1) // Un seul numéro trouvé = Résolution ok.
    			{
    				$this->_format[$anime][] = [
	    				'new' => $dir.$anime.' '.$final[0].$conflict['ext'],
	    				'old' => $conflict['old'],
	    				'num' => $final[0]
    				];
    			}
    			else 
    			{
    				if (count($final) > 0 && $_GET['multi'] === '1')
    				{
    					$this->_format[$anime][] = [
	    					'new' => $dir.$anime.' '.implode('-', $final).$conflict['ext'],
	    					'old' => $conflict['old'],
	    					'num' => $final
    					];
    				}
    				else // Résolution impossible.
    				{
	    				echo '<div class="original">'.basename($conflict['old']).'</div>';
	    				$err = '<div class="error">&#9888; Impossible de trouver le numéro de l\'épisode pour : '.basename($conflict['old']).'</div>';
	    				echo $err;
	    				$errors[] = $err;	
    				}
    				
    			}
    		}
    	}
    }
    
    /**
     * Vérifie la conformité des animes.
     */
    private function _control()
    {
        $this->_check();
        $this->_clean();
    }
    
    /**
     * Vérifie s'il manque un épisode.
     */
    private function _check()
    {
        echo '<h2>Episodes manquants</h2>';
        foreach ($this->_format as $anime => $data)
        {        	                        
            // Génération du tableau des numéros.
            $list = [];
            foreach ($data as $d)
            {
            	if (is_scalar($d['num']))
            	{
            		$list[] = $d['num']; 
            	}
            	else
            	{
            		foreach ($d['num'] as $nb)
            		{
            			$list[] = $nb;
            		}
            	}
            }  
            
            // Ordre croissant.
            sort($list);

            // Recherche des épisodes manquants.
            $missing = [];
            $count = count($list);
            for($i=1; $i < $count; $i++)
            {
                $e1 = $list[$i-1];
                $e2 = $list[$i];
                if ($e1 != $e2 && $e1 + 1 != $e2)
                {
                    $m = $e1 + 1;
                    while ($m != $e2)
                    {
                        $missing[] = $m++;
                    }
                }
            }
            
            if (count($missing))
            {
                echo '<div class="error">&#9888; Épisodes manquants pour '.$anime.' : '.implode(', ', $missing).'</div>';
            }
        }
    }

    /**
     * Supprime les doublons.
     */
    private function _clean()
    {
        echo '<h2>Doublons</h2>';
        foreach ($this->_format as $anime => &$data)
        {
            $doublons = [];
    
            // Génération du tableau des numéros.
            $list = [];
            foreach ($data as $d)
            {
            	if (is_scalar($d['num']))
            	{
            		$list[] = $d['num'];
            	}
            	else
            	{
            		foreach ($d['num'] as $nb)
            		{
            			$list[] = $nb;
            		}
            	}
            }
            
            // Ordre croissant.
            sort($list);
            
            // Flag sur la recherche de doublon, on boucle tant que l'on en trouve.
            $find = TRUE;
            while ($find)
            {
                $find = FALSE;
                $max = count($list);
                for($i=1; $i < $max; $i++)
                {
                    $e1 = $list[$i-1];
                    $e2 = $list[$i];
                    if ($e1 == $e2)
                    {
                        $doublons[] = $e1;
                        unset($list[$i-1]);
                        unset($list[$i]);
                        $i = $max;
                        $find = TRUE;
                    }
                }
                $list = array_values($list);
            }

            if (count($doublons) > 0)
            {
                echo '<div class="error">&#9888; Doublons trouvés pour '.$anime.' sur les épisodes : '.implode(', ', $doublons).'</div>';
            }
        }
    }
    
    /**
     * Renomme les animes.
     * @return bool
     */
    private function _rename()
    {
        echo '<h2>Résultat</h2>';
        if (!isset($_GET['run']) || $_GET['run'] !== '1')
        {
            echo '<div class="warning">Aucun fichier renommé, pour lancer le traitement il faut passer le paramètre GET run=1.</div>';
            return FALSE;
        }
        $total = 0;
        foreach ($this->_format as $anime => $data)
        {
            foreach ($data as $d)
            {
                if ($d['old'] != $d['new'])
                {
                    if (rename($d['old'], $d['new']))
                    {
                        $total++;
                    }
                    else
                    {
                        echo '<div class="error">Impossible de renommer : <br/>'.$d['old'].'<br/>'.$d['new'].'</div>';
                    }
                }
            }
        }
        echo '<div class="good">'.$total.' / '.$this->_count.' fichiers à renommer</div>';
        return FALSE;
    }
}
?>