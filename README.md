# php-daemon
PHP守护任务



1.什么是守护进程
守护进程是脱离于终端并且在后台运行的进程。守护进程脱离于终端是为了避免进程在执行过程中的信息在任何终端上显示并且进程也不会被任何终端所产生的终端信息所打断。
例如 apache, nginx, mysql 都是守护进程, 简而言之, 守护进程(daemon)就是一直在后台运行的进程(daemon)。


2.为什么开发守护进程
很多程序以服务形式存在, 他没有终端或UI交互, 它可能采用其他方式与其他程序交互, 如TCP/UDP Socket, UNIX Socket, fifo。程序一旦启动便进入后台, 直到满足条件他便开始处理任务。

3.何时采用守护进程开发应用程序
以我当前的需求为例, 我需要运行一个程序, 然后监听某端口, 持续接受服务端发起的数据, 然后对数据分析处理, 再将结果写入到数据库中; 我采用ZeroMQ实现数据收发。
如果我不采用守护进程方式开发该程序，程序一旦运行就会占用当前终端窗框，还有受到当前终端键盘输入影响，有可能程序误退出。

4.守护进程的安全问题
我们希望程序在非超级用户运行，这样一旦由于程序出现漏洞被骇客控制，攻击者只能继承运行权限，而无法获得超级用户权限。
我们希望程序只能运行一个实例，不运行同时开启两个以上的程序，因为会出现端口冲突等等问题。

使用场景
守护进程一般用于监控其他程序运行情况和执行定时任务。

创建守护进程
开始 -> fork()创建子进程 exit()使父进程退出 -> setsid() 创建新会话 -> chdir("/") 设置工作目录 -> umask(0) 重设文件权限掩码 close() 关闭文件描述符 -> 结束

下面以PHP的实现方式为例来说明。在说明之前先介绍几个PHP函数。如下：
pcntl_fork()：   在当前进程内创建一个子进程。成功时, 在父进程执行线程内返回产生的子进程的PID, 在子进程执行线程内返回0。失败时, 在父进程上下文返回-1, 不会创建子进程, 并且会引发一个PHP错误。
int pcntl_fork  ( void )
实例: 
<?php
$pid  =  pcntl_fork ();
 //父进程和子进程都会执行下面代码
if($pid  == - 1){
	//错误处理：创建子进程失败时返回-1.
	die( 'could not fork' );
}elseif( $pid ) {
	//父进程会得到子进程号，所以这里是父进程执行的逻辑
	pcntl_wait ( $status );  //等待子进程中断，防止子进程成为僵尸进程。
}else{
	//子进程得到的$pid为0, 所以这里是子进程执行的逻辑。
}

?>   

posix_setuid()： 设置当前进程的操作用户
bool posix_setuid  ( int $uid  )

posix_setgid()： 设置当前进程的操作用户所属分组
bool posix_setpgid  ( int $pid  , int $pgid  )

getmypid()：     获取当前 PHP 进程 ID。 获取当前 PHP 进程 ID。 
int getmypid  ( void )

posix_kill()：   向指定进程发送进程信号
bool posix_kill  ( int $pid  , int $sig  )


pcntl_signal()： 安装一个信号处理器
bool pcntl_signal  ( int $signo  , callback  $handler  [, bool $restart_syscalls  = true  ] )
signo   信号编号。 
handler 信号处理器可以是用户创建的函数或方法的名字, 也可以是系统常量 SIG_IGN (译注：忽略信号处理程序)或 SIG_DFL(默认信号处理程序) . 
Note: 注意当你使用一个对象方法的时候, 该对象的引用计数回增加使得它在你改变为其他处理或脚本结束之前是持久存在的。 
restart_syscalls  指定当信号到达时系统调用重启是否可用。


system()：       执行外部程序, 并且显示输出
string system  ( string $command  [, int &$return_var  ] )
<?php
echo  '<pre>';
$last_line  =  system ( 'ls' ,  $retval );
echo  '</pre>';
?>


PHP后台守护进程的实现方式(Linux环境)
应用场景
某些情况下, 我们需要持续的周期性的提供一些服务, 比如监控内存或cpu的运行状况, 这些应用与客户端是没有关系的, 不是说客户端(如web界面, 手机app等)关闭了, 我们就不监控内存或cpu了, 
为了应对这种业务场景, 后台守护进程就可以派上用场了。

所需环境
Linux

实现方式
1. 准备php脚本
在/usr/local/src/目录下, 新建一个daemon.php脚本文件, 内如如下：

参考代码

该脚本的作用, 就是每隔5秒, 向日志文件中写入一个时间戳, 当然, 这个只是一个简单的示例, 具体应用中, 我们还需要根据业务的不同, 编写具体的业务处理代码。

2. 以后台方式运行php脚本
在命令行下, 输入：
nohup php /usr/local/src/daemon.php &  

nohup: ignoring input and appending output to `nohup.out'
nohup: failed to run command `/etc/nginx_check.sh': Permission denied
说明没有权限
chmod +x /usr/local/src/daemon.php


nohup: ignoring input and appending output to ‘nohup.out’
如果只出现这种结果: 说明守护进程执行成功, 使用ctrl+c 退出


3. 查看日志输出
tail -f /usr/local/src/log.txt  

我们将会看到如下信息：
1471917997
1471918016
1471918026

4. 关闭php后台进程
首先, 我们需要查出该进程的PID, 命令：
ps -ef | grep "php /usr/local/src/daemon.php"  (常用的参数 ps -A | grep "daemon.php")


通过这个PID把该进程kill掉
kill -9 22767  
其中, 22767就是php后台进程的PID号。


5. 开机自启
通过前面的步骤, 我们知道如何开启和关闭一个php进程, 但是, 在实际的应用中, 我们不可能每次都是手动开启, 这样我们就会损失掉一部分业务数据, 所以我们必须要让该进程开机自动运行, 步骤如下：
在/etc/rc.local文件中, 将nohup php /usr/local/src/daemon.php &这个命令加入即可。





