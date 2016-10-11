<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

use App\Http\Requests;
use App\File;
use App\Folder;
use App\User;

class ApiController extends Controller
{
	private $ROOT = "";
	private $SECRET = "abc";

	private $DIR = array('folder' => 
		array(
			'id' => 0,
			'name' => "",
			'size' => 0
			// 'size_limit' => 0
			)
		);

	private $FILE = array('file' =>
		array(
			'id' => 0,
			'name' => "originalName",
			// 'extension' => 'ext',
			'size' => 0,
			// 'parent_id' => 0
			)
		);

	private $RESPONSE = array(
		'status' => array(
			'code' => 0,
			'message' => ''
			)
		// 'data' => array()
		);

	public function create_root($key, $user, $limit){
		$route = $this->ROOT;
		$size = 0;

		if ($key == $this->SECRET) { // CHEQUEAR API KEY SECRETA
			$user = User::find($user);

			if ($user != null) {
				if (!$this->folder_exist($user) && !Storage::exists($user->name)) {
					$folder = new Folder();
					$folder->name = $user->name;
					$folder->size = $size;
					$folder->route = $route;
					$folder->size_limit = $limit;
					$folder->save();
					Storage::makeDirectory($folder->name);
					$folder->user()->save($user);

					$this->DIR['folder']['id'] = $folder->id;
					$this->DIR['folder']['name'] = $folder->name;
					$this->DIR['folder']['size'] = $folder->size;
					$this->DIR['folder']['size_limit'] = $folder->size_limit;

					$this->RESPONSE['status']['code'] = 201;
					$this->RESPONSE['status']['message'] = "Folder successfully created";
					$this->RESPONSE['data'] = $this->DIR;
				} else {
					//ROOT FOLDER ALREADY EXIST
					$folder = $user->folder;
					$this->DIR['folder']['id'] = $folder->id;
					$this->DIR['folder']['name'] = $folder->name;
					$this->DIR['folder']['size'] = $folder->size;
					$this->DIR['folder']['size_limit'] = $folder->size_limit;

					$this->RESPONSE['status']['code'] = 200;
					$this->RESPONSE['status']['message'] = "This root folder already exists";
					$this->RESPONSE['data'] = $this->DIR;
				}
			} else {
				// RESPONSE USER ID
				$this->RESPONSE['status']['code'] = 422;
				$this->RESPONSE['status']['message'] = "You did not provided a valid User";
			}
		} else {
			// RESPONSE WRONG API KEY
			$this->RESPONSE['status']['code'] = 422;
			$this->RESPONSE['status']['message'] = "You did not provided a valid API key";
		}

		return $this->RESPONSE;
	}

	public function create_dir($key, $parent, $dirname){
		$route = $this->ROOT;
		$size = 0;

		$user = User::where("api_key", $key)->first();

		if ($user != null) { // CHEQUEAR API KEY
			$parent = Folder::find($parent);
			if ($parent != null){ 
				$route = $this->get_route($parent);
				$dirname = $dirname;
				if ($this->check_permission($key, $parent)) {
					if (!Storage::exists($route."/".$dirname)) {
						$folder = new Folder();
						$folder->name = $dirname;
						$folder->size = $size;
						$folder->route = $route;
						$folder->save();
						Storage::makeDirectory($folder->route."/".$folder->name);
						$parent->folders()->save($folder);

						$this->DIR['folder']['id'] = $folder->id;
						$this->DIR['folder']['name'] = $folder->name;
						$this->DIR['folder']['size'] = $folder->size;
						$this->DIR['folder']['parent_id'] = $folder->parent_id;

						$this->RESPONSE['status']['code'] = 201;
						$this->RESPONSE['status']['message'] = "Folder successfully created";
						$this->RESPONSE['data'] = $this->DIR;
					} else {
						// DIRECTORY ALREADY EXIST
						$folder = $parent->folders()->where('name',$dirname)->first();
						$this->DIR['folder']['id'] = $folder->id;
						$this->DIR['folder']['name'] = $folder->name;
						$this->DIR['folder']['size'] = $folder->size;
						$this->DIR['folder']['parent_id'] = $folder->parent_id;

						$this->RESPONSE['status']['code'] = 200;
						$this->RESPONSE['status']['message'] = "Folder already exists";
						$this->RESPONSE['data'] = $this->DIR;
					}
				} else {
					// WRONG PARENT FOLDER ID - PERMISSION ERROR
					$this->RESPONSE['status']['code'] = 403;
					$this->RESPONSE['status']['message'] = "You don't have the permissions to create a folder inside this folder";
				}
			} else {
				//WRONG PARENT FOLDER ID
				$this->RESPONSE['status']['code'] = 422;
				$this->RESPONSE['status']['message'] = "The Parent folder doesn't exists";
			}
		} else {
			// RESPONSE WRONG API KEY
			$this->RESPONSE['status']['code'] = 422;
			$this->RESPONSE['status']['message'] = "You did not provided a valid API key";
		}

		return $this->RESPONSE;
	}

	public function rm_dir($key, $folder){
		$user = User::where("api_key", $key)->first();

		if ($user != null) { // CHEQUEAR API KEY
			$folder = Folder::find($folder);

			if ($folder != null) {
				if ($folder->parent != null) {
					if ($this->check_permission($key, $folder)) {
						$this->clean_bd($folder);
						Storage::deleteDirectory($folder->route."/".$folder->name);
						$this->RESPONSE['status']['code'] = 200;
						$this->RESPONSE['status']['message'] = "Folder successfully deleted";
					} else {
					// ERROR DELETING FOLDER - PERMISSION ERROR
						$this->RESPONSE['status']['code'] = 403;
						$this->RESPONSE['status']['message'] = "You don't have the permissions to delete this folder";
					}
				} else {
					// 	ERROR YOU CANT DELETE A ROOT FOLDER
					$this->RESPONSE['status']['code'] = 403;
					$this->RESPONSE['status']['message'] = "You can't delete a root directory";
				}
			} else {
				// WRONG FOLDER ID
				$this->RESPONSE['status']['code'] = 422;
				$this->RESPONSE['status']['message'] = "The Folder doesn't exists";
			}
		} else {
			// RESPONSE WRONG API KEY
			$this->RESPONSE['status']['code'] = 422;
			$this->RESPONSE['status']['message'] = "You did not provided a valid API key";
		}
		return $this->RESPONSE;
	}

	public function list_folder($key, $folder){

		$user = User::where("api_key", $key)->first();
		$this->RESPONSE['data'] = [];

		if ($user != null) {
			$folder = Folder::find($folder);

			if ($folder != null) {
				$folders = $folder->folders;

				foreach ($folders as $exit) {
					$this->DIR['folder']['id'] = $exit->id;
					$this->DIR['folder']['name'] = $exit->name;
					$this->DIR['folder']['size'] = $exit->size;

					array_push($this->RESPONSE['data'], $this->DIR);
				}

				$this->RESPONSE['status']['code'] = 200;
				$this->RESPONSE['status']['message'] = "Folders listed successfully";
			} else {
				// WRONG FOLDER ID
				$this->RESPONSE['status']['code'] = 422;
				$this->RESPONSE['status']['message'] = "The Folder doesn't exists";
			}
		} else {
			// RESPONSE WRONG API KEY
			$this->RESPONSE['status']['code'] = 422;
			$this->RESPONSE['status']['message'] = "You did not provided a valid API key";
		}


		return $this->RESPONSE;
	}

	public function list_all($key, $folder){
		$user = User::where("api_key", $key)->first();
		$this->RESPONSE['data']['folders'] = [];
		$this->RESPONSE['data']['files'] = [];

		if ($user != null) {
			$folder = Folder::find($folder);

			if ($folder != null) {
				$folders = $folder->folders;
				$files = $folder->files;

				foreach ($folders as $exit) {
					$this->DIR['folder']['id'] = $exit->id;
					$this->DIR['folder']['name'] = $exit->name;
					$this->DIR['folder']['size'] = $exit->size;

					array_push($this->RESPONSE['data']['folders'], $this->DIR);
				}

				foreach ($files as $file) {
					$this->FILE['file']['id'] = $file->id;
					$this->FILE['file']['name'] = $file->originalName;
					$this->FILE['file']['size'] = $file->size;

					array_push($this->RESPONSE['data']['files'], $this->FILE);
				}

				$this->RESPONSE['status']['code'] = 200;
				$this->RESPONSE['status']['message'] = "Folders and Files listed successfully";
			} else {
				// WRONG FOLDER ID
				$this->RESPONSE['status']['code'] = 422;
				$this->RESPONSE['status']['message'] = "The Folder doesn't exists";
			}
		} else {
			// RESPONSE WRONG API KEY
			$this->RESPONSE['status']['code'] = 422;
			$this->RESPONSE['status']['message'] = "You did not provided a valid API key";
		}

		return $this->RESPONSE;
	}

	public function add_file($key, $folder, Request $request){
		$time = strtotime("now");
		$this->RESPONSE['data']=[];
		$route = $this->ROOT;
		$size = 0;
		$total = 0;
		$correct = 0;

		$user = User::where("api_key", $key)->first();

		if ($user != null) { // CHEQUEAR API KEY
			$folder = Folder::find($folder);

			if ($folder != null) {
				if ($this->check_permission($key, $folder)) {
					$all = true;
					$acum = 0;

					foreach ($request->file() as $file) {
						if (is_array($file)) {
							foreach ($file as $f) {
								$total++;
								$size = $f->getClientSize();

								if ($this->check_extension($f) && $f->isValid()) { // CHEQUEAR EXTENSION PERMITIDA && CHEQUEAR SUBIDA CORRECTA
									if ($this->check_remaining_space($folder, $acum+$size)){
										$correct++;
										$acum += $size;
										$filename="file".$time.$this->__randomStr ( 3 ).'.'.$f->extension();
										$route = $this->get_route($folder);

										$new = new File();
										$new->name = $filename;
										$new->extension = $f->extension();
										$new->originalName = $f->getClientOriginalName();
										$new->route = $route;
										$new->size = $size;
										$new->save();
										$f->storeAS($route, $filename);
										$folder->files()->save($new);

										if (in_array($f->getClientOriginalExtension(), ["jpg","png","jpeg"])) {
											$this->_compress($new->name, "storage/".$route);
										}

										$this->FILE['file']['id'] = $new->id;
										$this->FILE['file']['name'] = $new->originalName;
										$this->FILE['file']['extension'] = $new->extension;
										$this->FILE['file']['size'] = $new->size;
										$this->FILE['file']['parent_id'] = $folder->id;

										array_push($this->RESPONSE['data'], $this->FILE);
									} else {
										$all = false;
									}
								}
							}
						} else {
							$total++;
							$size = $file->getClientSize();

							if ($this->check_extension($file) && $file->isValid()) { // CHEQUEAR EXTENSION PERMITIDA && CHEQUEAR SUBIDA CORRECTA
								if ($this->check_remaining_space($folder, $acum+$size)){
									$correct++;
									$acum += $size;
									$filename="file".$time.$this->__randomStr ( 3 ).'.'.$file->extension();
									$route = $this->get_route($folder);

									$new = new File();
									$new->name = $filename;
									$new->extension = $file->extension();
									$new->originalName = $file->getClientOriginalName();
									$new->route = $route;
									$new->size = $size;
									$new->save();
									$file->storeAS($route, $filename);
									$folder->files()->save($new);

									if (in_array($file->getClientOriginalExtension(), ["jpg","png","jpeg"])) {
										$this->_compress($new->name, "storage/".$route);
									}

									$this->FILE['file']['id'] = $new->id;
									$this->FILE['file']['name'] = $new->originalName;
									$this->FILE['file']['extension'] = $new->extension;
									$this->FILE['file']['size'] = $new->size;
									$this->FILE['file']['parent_id'] = $folder->id;

									array_push($this->RESPONSE['data'], $this->FILE);
								} else {
									$all = false;
								}
							}
						}
					}

					// RECALCULATE SIZE
					$this->recalculate_size($folder, $acum);

					if ($all) {
						// ALL NEW FILES STORED
						$this->RESPONSE['status']['code'] = 201;
						$this->RESPONSE['status']['message'] = "Files successfully uploaded";
					} else {
						// SOME FILES STORED DUE TO INSUFFICIENT SPACE
						$this->RESPONSE['status']['code'] = 201;
						$this->RESPONSE['status']['message'] = "Space left insufficient to store all new files, ".$correct." of ".$total." successfully uploaded";
					}
				} else {
					// WRONG FOLDER ID - PERMISSION ERROR
					$this->RESPONSE['status']['code'] = 403;
					$this->RESPONSE['status']['message'] = "You don't have the permissions to upload to this folder";
				}
			} else {
				// FOLDER PROVIDED DOESN'T EXISTS
				$this->RESPONSE['status']['code'] = 422;
				$this->RESPONSE['status']['message'] = "The folder doesn't exists";
			}
		} else {
					// RESPONSE WRONG API KEY
			$this->RESPONSE['status']['code'] = 422;
			$this->RESPONSE['status']['message'] = "The API key you provided is not registered";
		}

		return $this->RESPONSE;
	}

	public function rm_file($key, $file){
		$file = File::find($file);

		if ($file != null) {
			$folder = $file->parent;

			if ($this->check_permission($key, $folder)) {
				Storage::delete($file->route."/".$file->name);
				$file->delete();

				$this->recalculate_size($folder, $file->size*(-1));

				$this->RESPONSE['status']['code'] = 200;
				$this->RESPONSE['status']['message'] = "File successfully deleted";
			} else {
				// ERROR DELETING FILE - PERMISSION ERROR
				$this->RESPONSE['status']['code'] = 403;
				$this->RESPONSE['status']['message'] = "You don't have the permissions to delete this file";
			}
		} else {
			// ERROR WRONG FILE ID PROVIDED
			$this->RESPONSE['status']['code'] = 422;
			$this->RESPONSE['status']['message'] = "This file doesn't exists";
		}

		return $this->RESPONSE;
	}

	public function list_file($key, $folder){

		$user = User::where("api_key", $key)->first();
		$this->RESPONSE['data'] = [];

		if ($user != null) {
			$folder = Folder::find($folder);

			if ($folder != null) {
				$files = $folder->files;

				foreach ($files as $file) {
					$this->FILE['file']['id'] = $file->id;
					$this->FILE['file']['name'] = $file->originalName;
					$this->FILE['file']['size'] = $file->size;

					array_push($this->RESPONSE['data'], $this->FILE);
				}

				$this->RESPONSE['status']['code'] = 200;
				$this->RESPONSE['status']['message'] = "Files listed successfully";
			} else {
				// WRONG FOLDER ID
				$this->RESPONSE['status']['code'] = 422;
				$this->RESPONSE['status']['message'] = "The Folder doesn't exists";
			}
		} else {
			// RESPONSE WRONG API KEY
			$this->RESPONSE['status']['code'] = 422;
			$this->RESPONSE['status']['message'] = "You did not provided a valid API key";
		}
		
		return $this->RESPONSE;
	}

	public function check_permission($key, $folder){ //VERIFICA QUE LA CARPETA PADRE PERTENEZCA AL ARBOL DE DICHO USUARIO
		$aux = $folder->parent;

		while ($aux != null) {
			$folder = $aux;
			$aux = $aux->parent;
		}

		$user = User::where("api_key", $key)->first();

		return $user->folder->id == $folder->id;
	}

	public function check_extension($file){
		return in_array($file->getClientOriginalExtension(), ['gif', 'jpg', 'png', 'jpeg', 'docx', 'doc', 'xls', 'xlsx', 'zip', 'rar']);
	}

	public function check_remaining_space($folder, $size){
		$aux = $folder->parent;

		while ($aux != null) {
			$folder = $aux;
			$aux = $aux->parent;
		}

		return $folder->size_limit >= $folder->size+$size;
	}

	public function recalculate_size($folder, $acum){
		if ($folder == null) {
			return true;
		} else {
			$this->recalculate_size($folder->parent, $acum);
			$folder->size+= $acum;
			$folder->save();

			return true;
		}
	}

	public function folder_exist($user){ // VERIFICA QUE LA CARPETA NO EXISTA EN BD
		return $user->folder != null;
	}

	public function get_route($parent){ // OBTIENE LA RUTA PARA LA CARPETA O ARCHIVO
		if ($parent->route == "") {
			return $parent->name;
		} else {
			return $parent->route."/".$parent->name;
		}
	}

	public function clean_bd($folder){ // ELIMINA DE LA BD TODAS LAS CARPETAS Y ARCHIVOS CONTENIDOS DENTRO DE $folder
		$folders = $folder->folders;
		$files = $folder->files;

		foreach ($files as $f) {
			File::find($f->id)->delete();
		}

		foreach ($folders as $f) {
			$this->clean_bd($f);
		}

		$folder->delete();
	}

	public  function __randomStr($length) {
		$str = '';
		$chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";

		$size = strlen ( $chars );
		for($i = 0; $i < $length; $i ++) {
			$str .= $chars [rand ( 0, $size - 1 )];
		}

		return $str;
	}

	public function test(){
		return view('add');
	}

	function _compress($file, $route){
		\Tinify\setKey("PTXJLwagfKt6l-r-DTnMNPRycYPVJqrY");

		$source = \Tinify\fromFile($route."/".$file);
		$source->toFile($route."/".$file);

		return true;
	}

}
