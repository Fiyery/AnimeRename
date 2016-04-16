<?php
class AnimeParser 
{
    /**
     * Liste des �l�ments sous forme de pattern � enlever dans le nom d'un anime.
     * @var array
     */
    private $_exclude_words = [];
    
    /**
     * Liste des �pisodes impossibles � num�roter.
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
     * @param string $dir Chemin des fichiers � traiter.
     */
    public function __construct($dir=NULL)
    {
        $this->add_dir($dir);
        $config = json_decode(file_get_contents(__DIR__.'/../config.json'));
        $this->_exclude_words = $config->excluded_patterns;
    }
    
    /**
     * Ajoute un dossier � traiter.
     * @param string $dir Chemin des fichiers � traiter.
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
        echo '<h2>D�tails</h2>';
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
	                    		$err = '<div class="error">&#9888; Impossible de trouver le num�ro de l\'�pisode pour : '.$f.'</div>';
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
            
            // Tentative de r�solution des conflits.
            if (isset($_GET['resolve']) && $_GET['resolve'] === '1')
            {
            	if (isset($this->_conflicts[$anime]))
            	{
            		foreach ($this->_conflicts[$anime] as &$conflict)
            		{
						preg_match_all('#\d+#', basename($conflict['new']), $num);
						$num = $num[0];
          				// On cherche si l'un des �pisodes n'existe pas d�j� de mani�re s�rement identifi�e.
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
           				if (count($final) === 1)
           				{
           					$this->_format[$anime][] = [
            					'new' => $dir.$anime.' '.$final[0].$ext,
            					'old' => $conflict['old'],
            					'num' => $final[0]
           					];
           				}
           				else // R�solution impossible.
           				{
           					echo '<div class="original">'.basename($conflict['old']).'</div>';
           					$err = '<div class="error">&#9888; Impossible de trouver le num�ro de l\'�pisode pour : '.basename($conflict['old']).'</div>';
           					echo $err;
           					$errors[] = $err;
           				}
          			}
           		}
           	}
                  
            // On recherche les �pisodes maximal pour le bourage de 0;
            if (isset($this->_format[$anime]))
            {
            	$max = 0;
            	foreach ($this->_format[$anime] as $ep)
            	{
            		$nb = number_format($ep['num']);
            		if ($nb > $max)
            		{
            			$max = $nb;
            		}
            	}
            	// Avec le num�ro de l'�pisode maximal, on connait le nombre de bourage � 0.
            	$nb_zero =  strlen($max);
            	$episodes = [];
            	foreach ($this->_format[$anime] as $k => $ep)
            	{
            		$episodes[$k] = [
						'old' => $ep['old']
            		];
            		$num = sprintf("%0".$nb_zero."d", $ep['num']);	
            		$episodes[$k]['new'] = dirname($ep['new']).'/'.preg_replace('#(\d+)\.#', $num.'.', basename($ep['new']));
            		$episodes[$k]['num'] = $num;
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
        }

        // R�cup�ration des d�tails d'affichage.
        $details = ob_get_clean();
        
        // Controle et r�cup�ration de l'affichage.
        ob_start();
        $this->_control();
        $control = ob_get_clean();
        
        // Affichage du r�sultat.
        $this->_rename();
        
        // Affichage des erreurs.
        echo '<h2>Formatage</h2><div class="error">'.implode('</div><div class="error">', $errors).'</div>';
        echo $control;
        echo $details;
    }
    
    /**
     * G�n�re la regex.
     * @param string $dir Chemin des fichiers � traiter.
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
     * Enl�ve les �l�ments pouvant g�ner le formatage du nom.
     * @param string $name Nom � r�duire.
     * @param string $anime Nom � r�duire.
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
     * R�cup�re l'extention.
     * @param string $name Nom du fichier.
     * @return string
     */
    private function _get_ext($name)
    {
        return substr($name, strrpos($name, '.'));
    }
    
    /**
     * R�cup�re le num�ro de l'�pisode.
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
     * V�rifie la conformit� des animes.
     */
    private function _control()
    {
        $this->_check();
        $this->_clean();
    }
    
    /**
     * V�rifie s'il manque un �pisode.
     */
    private function _check()
    {
        echo '<h2>Episodes manquants</h2>';
        foreach ($this->_format as $anime => $data)
        {
            $missing = [];
            if (!is_array($data))
            {
            	var_dump($anime);
            }
            $missing = [];
            usort($data, function($a, $b){
                return ($a['num'] > $b['num']);
            });
            $count = count($data);
            for($i=1; $i < $count; $i++)
            {
                $e1 = $data[$i-1]['num'];
                $e2 = $data[$i]['num'];
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
                echo '<div class="error">&#9888; �pisodes manquants pour '.$anime.' : '.implode(', ', $missing).'</div>';
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
    
            // Flag sur la recherche de doublon, on boucle tant que l'on en trouve.
            $find = TRUE;
            while ($find)
            {
                $find = FALSE;
                $max = count($data);
                for($i=1; $i < $max; $i++)
                {
                    $e1 = $data[$i-1]['num'];
                    $e2 = $data[$i]['num'];
                    if ($e1 == $e2)
                    {
                        $doublons[] = $e1;
                        unset($data[$i-1]);
                        unset($data[$i]);
                        $i = $max;
                        $find = TRUE;
                    }
                }
                $data = array_values($data);
            }

            if (count($doublons))
            {
                echo '<div class="error">&#9888; Doublons trouv�s pour '.$anime.' sur les �pisodes : '.implode(', ', $doublons).'</div>';
            }
        }
    }
    
    /**
     * Renomme les animes.
     * @return bool
     */
    private function _rename()
    {
        echo '<h2>R�sultat</h2>';
        if (!isset($_GET['run']) || $_GET['run'] !== '1')
        {
            echo '<div class="warning">Aucun fichier renomm�, pour lancer le traitement il faut passer le param�tre GET run=1.</div>';
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
        echo '<div class="good">'.$total.' / '.$this->_count.' fichiers � renommer</div>';
        return FALSE;
    }
}
?>