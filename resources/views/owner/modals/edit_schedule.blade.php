
  <div class="modal fade text-left" id="edit_schedule" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">

        <div class="modal-header">
          <div class="row w-100">
            <div class="col-10">
              <button form="form_edit_schedule" type="submit" class="btn btn-success btn_edit_schedule">スケジュール更新</button>
            </div>

            <div class="col-2">

              <div class="row">
                <div class="col-10 text-right">
                  <form id="form_del_schedule" action="{{route('schedule.del')}}" method="post" onSubmit="return submitDeleteSchedule(event)" class="">
                    <input id="delScheduleCsrfToken" type="hidden" name="_token" value="{{csrf_token()}}">
                    <button form="form_del_schedule" type="submit" class="btn btn_del">
                      <input type="hidden" name='message_id' class='msg_id'>
                      <i style="font-size:20px" class="fas fa-trash-alt text-muted"></i>
                    </button>
                  </form>
                </div>

                <div class="col-2">
                  <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
        

        <div class="modal-body">
          <form id="form_edit_schedule" action="{{ route('schedule.edit') }}" method="post" enctype="multipart/form-data" onSubmit="return submitEditSchedule(event)">
            {{-- @csrf --}}
            <input id="editScheduleCsrfToken" type="hidden" name="_token" value="{{csrf_token()}}">
            <p style="display:none" id="tmp_edit_schedule_data" data-msg-id="" data-is-change="" data-old-start="" data-old-end="" data-view-date=""></p>
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
          </form>
          
        </div>
      </div>
    </div>
  </div>

