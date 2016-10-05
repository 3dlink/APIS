@extends('layouts.app')

@section('content')

<div class="container">
	<div class="row">
		<div class="col-md-10 col-md-offset-1">
			<div class="panel panel-default">

				<div class="panel-heading center">Upload Files</div>


				<div class="panel-body">
					{!! Form::open(['route' => ['file.add', "abc",4], 'method' => 'POST', 'files' => 'true', 'class' => 'dropzone', 'id' => 'dropzoneForm']) !!}
				</div>

				<div class="panel-footer">
					{!! Form::submit('Save', array('id' => 'submit', 'class'=>'btn btn-primary')) !!}
				</div>

				{!! Form::close() !!}
			</div>
		</div>
	</div>
</div>
@endsection


@section('script')

<script type="text/javascript">
	Dropzone.options.dropzoneForm = ({
		url: "upload_file/abc/4", //4 admin y 7 admin/test
		paramName:'random',
		uploadMultiple: true,
		dictDefaultMessage: 'Click here...',
		addRemoveLinks: true,
		acceptedFiles: '.gif, .jpg, .png, .jpeg, .doc, .docx, .xls, .xlsx, .zip, .rar',
		parallelUploads: 3
	});
</script>

@endsection