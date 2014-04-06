less-css-compiler-minifier
==========================

Compile directory with css/less files to one file, using "lessphp" and "css-crush"

In a first place used as "External tool" in PHPStorm.

To use it, You will need to put directory in some place like

`~/phpstorm-tools/less/compiler/*`

Next step is to add new external tool in PHPStorm menu:
`ctrl+alt+s` -> `External Tools` -> `Alt+Insert`

Then fill fields as You wish... For example:

Name: `Compiler`
Group: `LESS`
Description: `Compiles less files in $filename.less directory`

Mark `Synchronize files after execution`

Program: `~your php env~` ( if file has no +x )
Parameters: `~/phpstorm-tools/less/compiler/compiler.php project=$ProjectFileDir$`
WorkingDirectory: `not in use`


**Also, you may want to use hotkey for compile output filse**

`ctrl+alt+s` -> `Keymap` -> `External Tools` -> `Your Group` -> `Your Tool Name`

RightClick on tool -> `Add Keyboard Shortcut` -> Press needed buttons ( for me it is `Ctrl+Shift+F9` )


**You can also use it as FileWatcher, but if you using autodeployment open**

`ctrl+alt+s` -> Projects settings `Deployment -> Options` 
Find `Upload changed files automaticaly to the default server` choose first or second value then check `Upload external changes`.

Btw, i think it can be not acceptable for some people.


Enjoy!
