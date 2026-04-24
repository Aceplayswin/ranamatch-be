<style>
    .matchRecordsItemView{
        display: flex;
        justify-content: space-between;
        padding: 15px 30px;
    }
    .matchRecordsItemView:nth-child(odd) {
        background: rgba(0,0,0,0.04);
    }
    .matchRecordsItemView div:first-child{
        height: 25px;
        width: 25px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: rgba(0,0,0,0.6);
        background: rgba(0,0,0,0.1);
        margin-right: 15px;
    }
    .matchRecordsItemView div:nth-child(2){
        flex: 1;
    }
    .matchRecordsItemView div:nth-child(2) p{
        font-size: 17px;
        color: #424949;
    }
    .matchRecordsItemView p span{
        color: #e67e22;
        text-transform: uppercase;
    }
    .matchRecordsItemView a{
        display: block;
        height: fit-content;
        padding: 5px 10px;
        border-radius: 5px;
        text-decoration: none;
        color: rgba(0,0,0,0.8);
        background: rgba(0,0,0,0.09);
    }
</style>

<div class="w-100 match_records_view">
</div>
