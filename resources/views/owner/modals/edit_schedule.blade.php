<form action="{{ route('schedule.edit') }}" method="post" enctype="multipart/form-data">
  @csrf
  <div class="modal fade text-left" id="edit_schedule" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <button type="submit" class="btn btn-primary btn_edit_schedule">スケジュール編集</button>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        
        <div class="modal-body">
          
          <div class="row mb-5">
            @include('owner.components.message_datatime')
          </div>

          <div class="row mb-3">
            @include('owner.components.message_title')
            <input type="hidden" name='message_id' class='msg_id'>
          </div>

          <div class="row mb-3">
            @include('owner.components.message_content')
          </div>

          <div class="row mb-3">
            @include('owner.components.message_image')
          </div>

        </div>
      </div>
    </div>
  </div>
</form>

