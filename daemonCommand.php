<?php
// Linux PHP守护进程/启动/停止/重启/查询状态
class ServiceDeamon {
    protected $pidfile;
    protected $logfile;
    protected $errorfile;
    protected $basedir;
    protected $tasks = array();
 
    public function __construct($pidfile = null) {
        $this->basedir = __DIR__;
        $this->logfile = $this->basedir . '/serviceDeamon.log';
        $this->errorfile = $this->basedir . '/serviceDeamon.err';
        if ($pidfile === null) {
            $this->pidfile = $this->basedir . '/serviceDeamon.pid';
        } else {
            $this->pidfile = $pidfile;
        }
    }
 
    public function deamonize() {
        /// fork and exit the parent
        $pid = pcntl_fork();
        if ($pid < 0 ) {
            die('could not fork');
        } else if ($pid > 0) {
            exit();  // kill the parent process
        }
 
        // detach from the terminal and become session leader
        if (posix_setsid() === -1) {
              die('could not detach from terminal');
        }
        // log current deamon process id
        file_put_contents($this->pidfile, posix_getpid());
        
        foreach ($this->tasks as $task) {
            $task();
        }
    }
 
    public function setLog($logfile) {
        $this->logfile = $logfile;
        return $this;
    } 
 
    public function setErorr($errorfile) {
        $this->errorfile = $errorfile;
        return $this;
    }
 
    public function getPid() {
        if (file_exists($this->pidfile)) {
            $pid = (int) file_get_contents($this->pidfile);
            if (posix_kill($pid, SIG_DFL)) {
                return $pid;
            } else {
                unlink($this->pidfile);
                return 0;
            }
        } else {
            return 0;
        }
    }
 
    public function start() {
        if ( ($pid = $this->getPid()) > 0 ) {
            echo "Process is running on PID: " . $pid . PHP_EOL;
        } else {
            echo "Starting ..." . PHP_EOL;
            $this->deamonize();
        }
    }
 
    public function stop() {
        if ( ($pid = $this->getPid()) > 0) {
            echo "Stopping..." . PHP_EOL;
            posix_kill($pid, SIGTERM);
            unlink($this->pidfile);
        } else {
            echo "Process not running yet!" . PHP_EOL;
        }
    }
 
    public function reload() {
        $this->stop();
        $this->start();
    }
 
    public function status() {
        if ( ($pid = $this->getPid()) > 0 ) {
            echo "Process is running on PID: " . $pid . PHP_EOL;
        } else {
            echo "Process not running yet!" . PHP_EOL;
        } 
    }
 
    public function run($argv) {
        $param = is_array($argv) && count($argv) == 2 ? $argv[1] : null;
        switch ($param) {
            case 'start':
                $this->start();
            break;
            case 'stop':
                $this->stop();
            break;
            case 'reload':
                $this->reload();
            break;
            case 'status':
                $this->status();
            break;
            default:
                echo "Unknown command!" . PHP_EOL .
                "Usage: " . $argv[0] . " start|stop|reload|status" . PHP_EOL;
            break;
        }
    }
 
    public function addService($servicename, callable $servicecallback) {
        $this->tasks[$servicename] = Closure::bind($servicecallback, $this, get_class());
    }
}
 
// add your own task
$serviceDeamon = new ServiceDeamon();
$serviceDeamon->addService('test', function(){
    $i = 0;
    while(true) {
        ++$i;
        //echo $i . ': this is a deamon' . PHP_EOL;
         
        file_put_contents(
            'echo_txt.txt',
            $i.': this is a deamon'.date('Y-m-d H:i:s').PHP_EOL,
            FILE_APPEND);
        sleep(10);
    }
});
$serviceDeamon->run($argv);
 
