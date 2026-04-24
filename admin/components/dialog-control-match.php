<style>
    .dialogView{
        background: rgba(0,0,0,0.6);
    }
    .dialogContents{
        position: absolute;
        left: 50%;
        top: 50%;
        width: 500px;
        min-height: 100px;
        padding: 15px 20px;
        border-radius: 15px;
        background: #FFFFFF;
        transform: translate(-50%,-50%);
    }
    
    .dialogContents span{
        font-size: 20px;
        font-weight: 600;
    }
    
    .controlOptionsView{
        width: 100%;
        display: flex;
        flex-wrap: wrap;
        margin-top: 30px;
    }
    .controlOptionsView input[type="radio"]{
        display: none;
    }
    .controlOption{
        cursor: pointer;
        padding: 5px 10px;
    }
    .controlOption label{
       display: block;
       text-align: center;
       min-width: 50px;
       padding: 10px 15px;
       background: #3498db;
    }
    
    input:checked + label {
        transform: scale(1.05);
    }
    
    @media (max-width: 500px) {
      .dialogContents{
        width: 95%;
      }  
    }
</style>
<div class="ps-fx top-0 lft-0 w-100 h-100vh dialogView hide_view">
    <div class="dialogContents col-view">
        <span>Control Game</span>
        <label class="ft-sz-12 mg-t-5">Select & Update the settings</label>
        
        <div class="controlOptionsView">
          
        </div>
        
        <div class="controlOption">
            <input type="number" name="inp_selected_setting" id="inp_selected_setting" placeholder="Enter Value.." class="w-100 cus-inp mg-t-10">
        </div>
        
        <div class="row-view j-start mg-t-50">
            <button class="action-btn ft-sz-18 pd-10-15 update_match_setting_btn">Update Setting&nbsp;<i class='bx bx-right-arrow-alt' ></i></button>
            <button class="action-btn ft-sz-18 pd-10-15 mg-l-10 bg-l-black-2 dialog_dismiss_btn">Close</button>
        </div>
    </div>
</div>

<script>
    let numbers_allowed;
    let selected_setting_value = "";
    var dialog_view = document.querySelector('.dialogView');
    var update_match_setting_btn = document.querySelector('.update_match_setting_btn');
    var inp_selected_setting = document.querySelector('#inp_selected_setting');
    var selected_setting = document.getElementsByName('selected_setting');
    var selected_setting_label = document.querySelectorAll('.controlOption label');
    var dialog_dismiss_btn = document.querySelector('.dialog_dismiss_btn');
    var control_options_view = document.querySelector('.controlOptionsView');
    
    function onSettingChange(){
      for(i = 0; i < selected_setting.length; i++) {
        if(selected_setting[i].checked){
            selected_setting_value = selected_setting[i].value;
        }
      }
    }
    
    function updateControlDialogOptions(data, numbers){
        numbers_allowed = numbers;
        control_options_view.innerHTML = data;
    }
    
    function showControlDialog(){
      dialog_view.classList.remove('hide_view');
      for(i = 0; i < selected_setting.length; i++) {
        selected_setting[i].addEventListener('change', onSettingChange);
      }
    }
    
    function hideDialog(){
        dialog_view.classList.add('hide_view');
    }
    
    update_match_setting_btn.addEventListener("click", () =>{
      let inp_setting = inp_selected_setting.value;
      if(numbers_allowed.length > 1){
        if((Number(inp_setting) < Number(numbers_allowed[0])) || (Number(inp_setting) > Number(numbers_allowed[1]))){
          inp_setting = "";
        }
      }else{
        inp_setting = "";  
      }
        
      if(inp_setting!="" && inp_setting!=undefined){
        selected_setting_value = inp_setting;
      }
      
      if(selected_setting_value!=""){
        hideDialog();
        onChangeSetting(selected_setting_value);
        selected_setting_value = "";
      }
    });
    
    dialog_dismiss_btn.addEventListener("click", () =>{
        hideDialog();
    })
    
</script>