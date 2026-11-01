package com.shop.inventory.grpc;

import com.shop.inventory.service.InventoryService;
import io.grpc.stub.StreamObserver;
import net.devh.boot.grpc.server.service.GrpcService;

@GrpcService
public class InventoryGrpcService extends InventoryServiceGrpc.InventoryServiceImplBase {

    private final InventoryService inventoryService;

    public InventoryGrpcService(InventoryService inventoryService) {
        this.inventoryService = inventoryService;
    }

    @Override
    public void getStock(GetStockRequest request, StreamObserver<GetStockResponse> responseObserver) {
      
        super.getStock(request, responseObserver);
    }

    @Override
    public void reserve(ReserveRequest request, StreamObserver<ReserveResponse> responseObserver) {
       
        super.reserve(request, responseObserver);
    }

    @Override
    public void release(ReleaseRequest request, StreamObserver<ReleaseResponse> responseObserver) {
       
        super.release(request, responseObserver);
    }

    @Override
    public void setStock(SetStockRequest request, StreamObserver<SetStockResponse> responseObserver) {
        inventoryService.setStock(request.getProductId(), request.getQuantity());
        SetStockResponse response = SetStockResponse.newBuilder().setOk(true).build();
        responseObserver.onNext(response);
        responseObserver.onCompleted();
    }
}